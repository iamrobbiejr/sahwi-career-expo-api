<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define permissions grouped by category
        $permissions = [
            // SYSTEM
            ['name' => 'system.manage_all', 'description' => 'Full system access', 'category' => 'system'],
            ['name' => 'system.view_analytics', 'description' => 'View analytics dashboard', 'category' => 'system'],
            ['name' => 'system.export_reports', 'description' => 'Export reports', 'category' => 'system'],
            ['name' => 'system.manage_settings', 'description' => 'Manage platform settings', 'category' => 'system'],
            // USERS
            ['name' => 'users.view', 'description' => 'View user profiles', 'category' => 'users'],
            ['name' => 'users.edit_self', 'description' => 'Edit own profile', 'category' => 'users'],
            ['name' => 'users.edit_any', 'description' => 'Edit any user', 'category' => 'users'],
            ['name' => 'users.verify_accounts', 'description' => 'Verify accounts', 'category' => 'users'],
            ['name' => 'users.suspend', 'description' => 'Suspend users', 'category' => 'users'],
            // ORGANIZATIONS
            ['name' => 'org.create', 'description' => 'Create organization', 'category' => 'organizations'],
            ['name' => 'org.edit', 'description' => 'Edit organization', 'category' => 'organizations'],
            ['name' => 'org.verify', 'description' => 'Verify organization', 'category' => 'organizations'],
            ['name' => 'org.add_members', 'description' => 'Add organization members', 'category' => 'organizations'],
            ['name' => 'org.remove_members', 'description' => 'Remove organization members', 'category' => 'organizations'],
            // EVENTS
            ['name' => 'events.create', 'description' => 'Create events', 'category' => 'events'],
            ['name' => 'events.edit', 'description' => 'Edit events', 'category' => 'events'],
            ['name' => 'events.publish', 'description' => 'Publish events', 'category' => 'events'],
            ['name' => 'events.delete', 'description' => 'Delete events', 'category' => 'events'],
            ['name' => 'events.register', 'description' => 'Register for events', 'category' => 'events'],
            ['name' => 'events.register_company', 'description' => 'Register company for events', 'category' => 'events'],
            ['name' => 'events.view_registrations', 'description' => 'View event registrations', 'category' => 'events'],
            ['name' => 'events.manage_panels', 'description' => 'Manage event panels', 'category' => 'events'],
            ['name' => 'events.create_conference', 'description' => 'Create conference calls', 'category' => 'events'],
            // TALKS
            ['name' => 'talks.create', 'description' => 'Create talks', 'category' => 'talks'],
            ['name' => 'talks.edit', 'description' => 'Edit talks', 'category' => 'talks'],
            ['name' => 'talks.delete', 'description' => 'Delete talks', 'category' => 'talks'],
            ['name' => 'talks.approve', 'description' => 'Approve talks', 'category' => 'talks'],
            // ARTICLES
            ['name' => 'articles.create', 'description' => 'Create articles', 'category' => 'articles'],
            ['name' => 'articles.edit', 'description' => 'Edit articles', 'category' => 'articles'],
            ['name' => 'articles.publish', 'description' => 'Publish articles', 'category' => 'articles'],
            ['name' => 'articles.delete', 'description' => 'Delete articles', 'category' => 'articles'],
            ['name' => 'articles.comment', 'description' => 'Comment on articles', 'category' => 'articles'],
            ['name' => 'articles.moderate_comments', 'description' => 'Moderate article comments', 'category' => 'articles'],
            // MESSAGING
            ['name' => 'messages.send', 'description' => 'Send messages', 'category' => 'messaging'],
            ['name' => 'messages.receive', 'description' => 'Receive messages', 'category' => 'messaging'],
            ['name' => 'messages.create_threads', 'description' => 'Create message threads', 'category' => 'messaging'],
            ['name' => 'messages.moderate', 'description' => 'Moderate messages', 'category' => 'messaging'],
            // FORUMS
            ['name' => 'forums.view', 'description' => 'View forums', 'category' => 'forums'],
            ['name' => 'forums.post', 'description' => 'Create forum posts', 'category' => 'forums'],
            ['name' => 'forums.comment', 'description' => 'Comment in forums', 'category' => 'forums'],
            ['name' => 'forums.moderate', 'description' => 'Moderate forum content', 'category' => 'forums'],
            ['name' => 'forums.manage', 'description' => 'Manage forums', 'category' => 'forums'],
            // PAYMENTS & DONATIONS
            ['name' => 'payments.make', 'description' => 'Make payments', 'category' => 'payments'],
            ['name' => 'payments.refund', 'description' => 'Refund payments', 'category' => 'payments'],
            ['name' => 'donations.make', 'description' => 'Make donations', 'category' => 'donations'],
            ['name' => 'donations.manage', 'description' => 'Manage donation campaigns', 'category' => 'donations'],
            ['name' => 'donations.view_reports', 'description' => 'View donation reports', 'category' => 'donations'],
        ];
        // Create permissions
        foreach ($permissions as $permissionData) {
            Permission::firstOrCreate(
                ['name' => $permissionData['name']],
                [
                    'description' => $permissionData['description'],
                    'category' => $permissionData['category']
                ]
            );
        }
        // Assign permissions to roles
        $this->assignPermissions();
    }
    private function assignPermissions()
    {
        // Admin gets all permissions
        $admin = Role::findByName('admin');
        if ($admin) {
            $admin->givePermissionTo(Permission::all());
        }
        // Student permissions
        $student = Role::findByName('student');
        if ($student) {
            $studentPermissions = [
                'users.edit_self',
                'events.register',
                'articles.comment',
                'forums.view',
                'forums.post',
                'forums.comment',
                'messages.send',
                'messages.receive',
                'donations.make'
            ];
            $student->givePermissionTo($studentPermissions);
        }
        // Professional permissions
        $professional = Role::findByName('professional');
        if ($professional) {
            $professionalPermissions = [
                'users.edit_self',
                'events.register',
                'talks.create',
                'articles.comment',
                'messages.send',
                'messages.receive',
                'forums.post',
                'donations.make'
            ];
            $professional->givePermissionTo($professionalPermissions);
        }
        // Company Rep permissions
        $companyRep = Role::findByName('company_rep');
        if ($companyRep) {
            $companyRepPermissions = [
                'users.edit_self',
                'org.create',
                'org.edit',
                'org.add_members',
                'events.register_company',
                'talks.create',
                'messages.send',
                'messages.receive'
            ];
            $companyRep->givePermissionTo($companyRepPermissions);
        }
        // University permissions
        $university = Role::findByName('university');
        if ($university) {
            $universityPermissions = [
                'users.edit_self',
                'org.create',
                'org.edit',
                'talks.create',
                'events.create',
                'messages.send',
                'messages.receive',
                'articles.create'
            ];
            $university->givePermissionTo($universityPermissions);
        }
    }
}
