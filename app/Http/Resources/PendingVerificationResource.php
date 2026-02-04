<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PendingVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'role' => $this->role,
            'verified' => $this->verified,
            'verification_submitted_at' => $this->verification_submitted_at,
            'verification_reviewed_at' => $this->verification_reviewed_at,
            'interested_university_id' => $this->interested_university_id,
            'expert_field' => $this->expert_field,
            'current_school_name' => $this->current_school_name,
            'current_grade' => $this->current_grade,
            'dob' => $this->dob,
            'bio' => $this->bio,
            'organisation_id' => $this->organisation_id,
            'title' => $this->title,
            'whatsapp_number' => $this->whatsapp_number,
            'interested_area' => $this->interested_area,
            'interested_course' => $this->interested_course,
            'avatar_url' => $this->avatar_url,
            'reputation_points' => $this->reputation_points,
            'streak_days' => $this->streak_days,
            'streak_last_date' => $this->streak_last_date,
        ];

        // Add professional verification docs for roles that require verification
        if (in_array($this->role->value, ['professional', 'company_rep', 'university'])) {
            $data['professional_verification_docs'] = $this->professional_verification_docs;
        }

        // Add organization details for company_rep and university roles
        if (in_array($this->role->value, ['company_rep', 'university']) && $this->organisation) {
            $data['organization'] = [
                'id' => $this->organisation->id,
                'name' => $this->organisation->name,
                'type' => $this->organisation->type,
                'verified' => $this->organisation->verified,
                'verification_docs' => $this->organisation->verification_docs,
            ];
        }

        return $data;
    }
}
