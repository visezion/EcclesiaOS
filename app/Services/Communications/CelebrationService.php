<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\CelebrationDispatch;
use App\Models\CelebrationSetting;
use App\Models\Family;
use App\Models\Member;
use App\Support\Branding;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class CelebrationService
{
    private const DEFAULTS = [
        'enabled' => true,
        'birthdays_enabled' => true,
        'anniversaries_enabled' => true,
        'celebrant_channels' => ['in_app', 'email', 'sms', 'whatsapp'],
        'send_time' => '09:00',
        'birthday_subject' => 'Happy Birthday, {{celebrantName}}!',
        'birthday_message' => 'Happy birthday, {{celebrantName}}! We thank God for your life and pray that this new year brings you joy, strength, and abundant blessings.\n\nCelebrate well, from your church family!\n{{imageUrl}}',
        'birthday_group_message' => 'Church family, please join us in celebrating {{celebrantName}} on their birthday! Send them love, prayers, and warm wishes.\n\n{{imageUrl}}',
        'anniversary_subject' => 'Happy Wedding Anniversary, {{celebrantName}}!',
        'anniversary_message' => 'Happy wedding anniversary to {{celebrantName}}! May God continue to bless your home with love, grace, friendship, and many beautiful years together.\n\nWith love from your church family!\n{{imageUrl}}',
        'anniversary_group_message' => 'Church family, please celebrate {{celebrantName}} on their wedding anniversary! Let us honour their journey with prayers, love, and joyful congratulations.\n\n{{imageUrl}}',
        'design' => ['frame' => 'sunrise', 'accent' => '#7c3aed', 'background' => '#fff7ed', 'footer' => 'With love from your church family'],
    ];

    public function __construct(
        private readonly DomainNotificationService $notifications,
        private readonly ZenderWhatsAppNotifier $whatsapp,
    ) {}

    public function settings(int $churchId): CelebrationSetting
    {
        return CelebrationSetting::query()->firstOrCreate(
            ['church_id' => $churchId],
            self::DEFAULTS,
        );
    }

    /** @return array{birthdays: int, anniversaries: int, sent: int, skipped: int, failed: int} */
    public function dispatchDue(?int $churchId = null, ?Carbon $date = null): array
    {
        $date ??= now();
        $summary = ['birthdays' => 0, 'anniversaries' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
        $churchIds = $churchId !== null
            ? collect([$churchId])
            : CelebrationSetting::query()->where('enabled', true)->pluck('church_id');

        foreach ($churchIds as $id) {
            $settings = $this->settings((int) $id);
            if (! $settings->enabled || ! $this->isDue($settings, $date)) {
                continue;
            }

            if ($settings->birthdays_enabled) {
                $this->birthdays((int) $id, $settings, $date, $summary);
            }
            if ($settings->anniversaries_enabled) {
                $this->anniversaries((int) $id, $settings, $date, $summary);
            }
        }

        return $summary;
    }

    private function birthdays(int $churchId, CelebrationSetting $settings, Carbon $date, array &$summary): void
    {
        Member::query()
            ->with(['memberProfile', 'family', 'userAccount'])
            ->where('church_id', $churchId)
            ->where('status', 'active')
            ->whereHas('memberProfile', fn ($query) => $query->whereMonth('date_of_birth', $date->month)->whereDay('date_of_birth', $date->day))
            ->get()
            ->each(function (Member $member) use ($settings, $date, &$summary): void {
                $summary['birthdays']++;
                $this->celebrateMember($member, 'birthday', $settings, $date, null, $summary);
            });
    }

    private function anniversaries(int $churchId, CelebrationSetting $settings, Carbon $date, array &$summary): void
    {
        $members = Member::query()
            ->with(['memberProfile', 'family.members.memberProfile', 'family.primaryContact', 'userAccount'])
            ->where('church_id', $churchId)
            ->where('status', 'active')
            ->whereHas('memberProfile', fn ($query) => $query->whereMonth('anniversary_date', $date->month)->whereDay('anniversary_date', $date->day))
            ->get();

        $members->groupBy(fn (Member $member) => $member->family_id ? 'family:'.$member->family_id : 'member:'.$member->id)
            ->each(function ($celebrants) use ($settings, $date, &$summary): void {
                /** @var Member $first */
                $first = $celebrants->first();
                $family = $first->family;
                $years = $this->yearsSince($first->memberProfile?->anniversary_date, $date);
                $summary['anniversaries']++;

                foreach ($celebrants as $member) {
                    $this->celebrateMember($member, 'anniversary', $settings, $date, $family, $summary, $years, false);
                }

                $this->celebrateGroup($first, 'anniversary', $settings, $date, $family, $years, $summary);
            });
    }

    private function celebrateMember(
        Member $member,
        string $occasion,
        CelebrationSetting $settings,
        Carbon $date,
        ?Family $family,
        array &$summary,
        ?int $years = null,
        bool $sendGroup = true,
    ): void {
        $dispatch = $this->claim($member->church_id, $settings, $member, $family, $occasion, $date, $years);
        if ($dispatch === null) {
            $summary['skipped']++;

            return;
        }

        $name = $this->displayName($member);
        $familyName = $family?->name ?? $member->family?->name ?? '';
        $imageUrl = $this->imageUrl($dispatch->image_path);
        $variables = [
            '{{celebrantName}}' => $name,
            '{{familyName}}' => $familyName,
            '{{years}}' => (string) ($years ?? $this->yearsSince($member->memberProfile?->date_of_birth, $date)),
            '{{occasionDate}}' => $date->format('F j'),
            '{{imageUrl}}' => $imageUrl,
        ];
        $subject = $this->replace($occasion === 'birthday' ? $settings->birthday_subject : $settings->anniversary_subject, $variables);
        $message = $this->replace($occasion === 'birthday' ? $settings->birthday_message : $settings->anniversary_message, $variables);
        $channels = $settings->celebrant_channels ?: self::DEFAULTS['celebrant_channels'];
        $deliveries = $this->notifications->member(
            $member,
            $occasion === 'birthday' ? 'BirthdayCelebration' : 'WeddingAnniversaryCelebration',
            'celebrations',
            $subject,
            $message,
            $channels,
            ['image_url' => $imageUrl, 'celebration_image_path' => $dispatch->image_path, 'url' => route('account.settings')],
        );
        $failed = $deliveries->contains('status', 'failed');
        $dispatch->update(['status' => $failed ? 'failed' : 'sent', 'metadata' => ['delivery_ids' => $deliveries->pluck('id')->all()]]);
        $failed ? $summary['failed']++ : $summary['sent']++;

        if ($sendGroup) {
            $this->celebrateGroup($member, $occasion, $settings, $date, $family, $years, $summary);
        }
    }

    private function celebrateGroup(Member $member, string $occasion, CelebrationSetting $settings, Carbon $date, ?Family $family, ?int $years, array &$summary): void
    {
        $dispatch = $this->claim($member->church_id, $settings, null, $family, $occasion.'-group', $date, $years, $member);
        if ($dispatch === null) {
            return;
        }

        $name = $family?->name ?? $this->displayName($member);
        $variables = [
            '{{celebrantName}}' => $name,
            '{{familyName}}' => $family?->name ?? '',
            '{{years}}' => (string) ($years ?? ''),
            '{{occasionDate}}' => $date->format('F j'),
            '{{imageUrl}}' => $this->imageUrl($dispatch->image_path),
        ];
        $message = $this->replace($occasion === 'birthday' ? $settings->birthday_group_message : $settings->anniversary_group_message, $variables);
        $result = $this->whatsapp->notify(
            (int) $member->church_id,
            $message,
            $occasion === 'birthday' ? 'BirthdayCelebrationGroup' : 'WeddingAnniversaryCelebrationGroup',
            $member->campus_id,
            null,
            $occasion === 'birthday' ? 'Birthday celebration: '.$name : 'Wedding anniversary: '.$name,
        );
        $failed = ($result['failed'] ?? 0) > 0;
        $dispatch->update(['status' => $failed ? 'failed' : (($result['sent'] ?? 0) > 0 ? 'sent' : 'skipped'), 'metadata' => $result]);
        $summary[$failed ? 'failed' : (($result['sent'] ?? 0) > 0 ? 'sent' : 'skipped')]++;
    }

    private function claim(int $churchId, CelebrationSetting $settings, ?Member $member, ?Family $family, string $occasion, Carbon $date, ?int $years, ?Member $imageMember = null): ?CelebrationDispatch
    {
        try {
            $existing = CelebrationDispatch::query()
                ->where('church_id', $churchId)
                ->where('occasion_type', $occasion)
                ->whereDate('occasion_date', $date->toDateString())
                ->when($member, fn ($query) => $query->where('member_id', $member->id), fn ($query) => $query->whereNull('member_id'))
                ->when($family, fn ($query) => $query->where('family_id', $family->id), fn ($query) => $query->whereNull('family_id'))
                ->first();

            if ($existing) {
                return null;
            }

            return CelebrationDispatch::query()->create([
                'church_id' => $churchId,
                'occasion_type' => $occasion,
                'member_id' => $member?->id,
                'family_id' => $family?->id,
                'occasion_date' => $date->toDateString(),
                'celebration_setting_id' => $settings->id,
                'years' => $years,
                'image_path' => $this->renderCard($member ?? $imageMember, $family, $occasion, $settings, $date, $years),
                'status' => 'queued',
            ]);
        } catch (QueryException) {
            return null;
        }
    }

    private function renderCard(?Member $member, ?Family $family, string $occasion, CelebrationSetting $settings, Carbon $date, ?int $years): string
    {
        $member ??= $family?->primaryContact;
        $name = $family?->name ?? ($member ? $this->displayName($member) : 'Our church family');
        $photo = $family?->celebration_photo_path ?: $member?->profile_photo_path;
        $photoUrl = $photo ? $this->imageUrl($photo) : ($member?->userAccount?->avatar_src ?? null);
        $design = array_replace(self::DEFAULTS['design'], $settings->design ?? []);
        $branding = new Branding($settings->church, is_array($settings->church?->settings) ? $settings->church->settings : []);
        $brandName = htmlspecialchars($branding->churchName(), ENT_QUOTES, 'UTF-8');
        $accent = htmlspecialchars((string) $design['accent'], ENT_QUOTES, 'UTF-8');
        $background = htmlspecialchars((string) $design['background'], ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeFooter = htmlspecialchars((string) $design['footer'], ENT_QUOTES, 'UTF-8');
        $label = $occasion === 'anniversary' || str_contains($occasion, 'anniversary') ? 'Happy Wedding Anniversary' : 'Happy Birthday';
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $yearsText = $years ? '<text x="600" y="450" text-anchor="middle" font-family="Arial,sans-serif" font-size="30" fill="'.$accent.'">'.(int) $years.' beautiful years</text>' : '';
        $image = $photoUrl ? '<image href="'.htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8').'" x="450" y="95" width="300" height="300" preserveAspectRatio="xMidYMid slice" clip-path="url(#circle)" />' : '<circle cx="600" cy="245" r="150" fill="#f5e8ff"/><text x="600" y="260" text-anchor="middle" font-family="Arial,sans-serif" font-size="80" font-weight="700" fill="'.$accent.'">'.htmlspecialchars(Str::upper(Str::substr($name, 0, 1)), ENT_QUOTES, 'UTF-8').'</text>';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630"><defs><clipPath id="circle"><circle cx="600" cy="245" r="150"/></clipPath><linearGradient id="wash" x1="0" y1="0" x2="1" y2="1"><stop stop-color="'.$background.'"/><stop offset="1" stop-color="#ffffff"/></linearGradient></defs><rect width="1200" height="630" rx="48" fill="url(#wash)"/><circle cx="80" cy="80" r="130" fill="'.$accent.'" opacity=".10"/><circle cx="1120" cy="570" r="190" fill="'.$accent.'" opacity=".10"/><rect x="24" y="24" width="1152" height="582" rx="36" fill="none" stroke="'.$accent.'" stroke-width="6" opacity=".55"/><text x="600" y="62" text-anchor="middle" font-family="Arial,sans-serif" font-size="18" font-weight="700" letter-spacing="3" fill="'.$accent.'">'.$brandName.'</text>'.$image.'<text x="600" y="535" text-anchor="middle" font-family="Arial,sans-serif" font-size="44" font-weight="800" fill="#172033">'.$safeLabel.'</text><text x="600" y="580" text-anchor="middle" font-family="Arial,sans-serif" font-size="26" fill="#475569">'.$safeName.'</text>'.$yearsText.'<text x="600" y="610" text-anchor="middle" font-family="Arial,sans-serif" font-size="16" fill="#64748b">'.$safeFooter.'</text></svg>';
        $path = 'celebrations/'.$date->format('Y').'/'.Str::slug($occasion).'-'.($member?->id ?? $family?->id).'-'.$date->format('md').'.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }

    private function imageUrl(?string $path): string
    {
        return $path ? asset('storage/'.ltrim($path, '/')) : '';
    }

    private function replace(?string $template, array $variables): string
    {
        return strtr($template ?: '', $variables);
    }

    private function displayName(Member $member): string
    {
        return trim($member->memberProfile?->preferred_name ?: $member->first_name.' '.$member->last_name);
    }

    private function yearsSince(mixed $date, Carbon $today): ?int
    {
        return $date ? (int) Carbon::parse($date)->diffInYears($today) : null;
    }

    private function isDue(CelebrationSetting $settings, Carbon $now): bool
    {
        $sendTime = Carbon::parse($now->toDateString().' '.($settings->send_time ?: '09:00'), $now->timezone);

        return $now->greaterThanOrEqualTo($sendTime);
    }
}
