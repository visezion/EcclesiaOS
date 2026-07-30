<?php

return [
    'retention_days' => (int) env('MESSAGE_RETENTION_DAYS', 2555),
    'max_attachment_kb' => (int) env('MESSAGE_MAX_ATTACHMENT_KB', 20480),
    'max_attachments' => (int) env('MESSAGE_MAX_ATTACHMENTS', 10),
    'allow_cross_campus' => (bool) env('MESSAGE_ALLOW_CROSS_CAMPUS', true),
    'response_reminder_hours' => (int) env('MESSAGE_RESPONSE_REMINDER_HOURS', 24),
];
