<?php

namespace App\Enums;

enum UserRole: string
{
     case ADMIN = "admin";
    case STUDENT = 'student';
    case PROFESSIONAL = 'professional';
    case COMPANY_REP = 'company_rep';
    case UNIVERSITY = 'university';
}
