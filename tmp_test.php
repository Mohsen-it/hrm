<?php

// Test: what happens with the exact validation rules for rotation_assignment
use Illuminate\Support\Facades\Validator;

// Scenario 1: User selects "assign" with valid data
$data1 = [
    'name' => 'System Super Admin',
    'email' => 'admin@hrm.local',
    'status' => 1,
    'rotation_assignment' => [
        'action' => 'assign',
        'rotation_id' => '1',
        'rotation_group_id' => '1',
        'start_date' => '2026-07-28',
        'end_date' => '',
    ],
];

// Use the actual rules from UpdateUserRequest
$rules = [
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'email', 'max:255'],
    'status' => ['required', 'integer', 'in:0,1'],
    'rotation_assignment' => ['nullable', 'array'],
    'rotation_assignment.action' => ['nullable', 'in:assign,transfer,unassign'],
    'rotation_assignment.rotation_id' => ['required_with:rotation_assignment', 'integer', 'exists:att_rotations,id'],
    'rotation_assignment.rotation_group_id' => ['required_if:rotation_assignment.action,assign,transfer', 'integer', 'exists:att_rotation_groups,id'],
    'rotation_assignment.start_date' => ['required_if:rotation_assignment.action,assign,transfer', 'date'],
    'rotation_assignment.end_date' => ['nullable', 'date'],
];

$v = Validator::make($data1, $rules);
if ($v->fails()) {
    echo 'FAIL: '.json_encode($v->errors()->toArray()).PHP_EOL;
} else {
    echo 'PASS'.PHP_EOL;
}

// Scenario 2: What if rotation_id is empty string?
$data2 = $data1;
$data2['rotation_assignment']['rotation_id'] = '';
$v2 = Validator::make($data2, $rules);
if ($v2->fails()) {
    echo 'Empty rotation_id FAIL: '.json_encode($v2->errors()->toArray()).PHP_EOL;
} else {
    echo 'Empty rotation_id PASS'.PHP_EOL;
}

// Scenario 3: What if rotation_group_id is empty?
$data3 = $data1;
$data3['rotation_assignment']['rotation_group_id'] = '';
$v3 = Validator::make($data3, $rules);
if ($v3->fails()) {
    echo 'Empty group_id FAIL: '.json_encode($v3->errors()->toArray()).PHP_EOL;
} else {
    echo 'Empty group_id PASS'.PHP_EOL;
}

// Scenario 4: What if start_date is empty?
$data4 = $data1;
$data4['rotation_assignment']['start_date'] = '';
$v4 = Validator::make($data4, $rules);
if ($v4->fails()) {
    echo 'Empty start_date FAIL: '.json_encode($v4->errors()->toArray()).PHP_EOL;
} else {
    echo 'Empty start_date PASS'.PHP_EOL;
}
