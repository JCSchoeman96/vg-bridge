<?php

declare(strict_types=1);

if (!function_exists('ld_update_group_access')) {
    function ld_update_group_access(int $user_id, int $group_id, bool $remove = false): void
    {
        $GLOBALS['vgcb_test_group_access_calls'][] = [
            'user_id' => $user_id,
            'group_id' => $group_id,
            'remove' => $remove,
        ];
    }
}

if (!function_exists('ld_update_course_access')) {
    function ld_update_course_access(int $user_id, int $course_id, bool $remove = false): void
    {
        $GLOBALS['vgcb_test_course_access_calls'][] = [
            'user_id' => $user_id,
            'course_id' => $course_id,
            'remove' => $remove,
        ];
    }
}
