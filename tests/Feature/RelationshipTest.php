<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Church;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_database_relationships_work(): void
    {
        $church = Church::factory()->create();
        $campus = Campus::factory()->create(['church_id' => $church->id]);
        Member::factory()->create(['church_id' => $church->id, 'campus_id' => $campus->id]);

        $this->assertTrue($church->campuses()->whereKey($campus->id)->exists());
        $this->assertTrue($church->members()->exists());
    }
}
