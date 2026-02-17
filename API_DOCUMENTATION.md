# SAHWI Career Expo API Documentation

## 📚 Overview

This document provides comprehensive information about the SAHWI Career Expo API endpoints, authentication, and usage
guidelines.

## 🔗 Accessing the API Documentation

### Swagger UI Documentation

Once the application is running, you can access the interactive API documentation at:

```
http://localhost:8000/api/documentation
```

**Note:** If you encounter any issues generating the Swagger documentation, run:

```bash
php artisan l5-swagger:generate
```

### Alternative: Postman Collection

You can also import the API endpoints into Postman for testing. See the `postman_collection.json` file in the project
root.

## 🔐 Authentication

The API uses **Laravel Sanctum** for authentication with Bearer tokens.

### Getting Started

1. **Register a new account** (POST `/api/v1/auth/register`)
2. **Login** to receive an access token (POST `/api/v1/auth/login`)
3. **Include the token** in subsequent requests:
   ```
   Authorization: Bearer {your-token-here}
   ```

### Example Authentication Flow

#### 1. Register

```http
POST /api/v1/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePass123!",
  "role": "student",
  "current_school_name": "ABC High School",
  "current_grade": "Grade 12",
  "interested_area": "Computer Science",
  "interested_course": "BSc Computer Science",
  "interested_university_id": 1
}
```

**Response:**

```json
{
    "message": "Registration successful. Please check your email to verify your account.",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "student"
    }
}
```

#### 2. Login

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "SecurePass123!"
}
```

**Response:**

```json
{
    "message": "Logged in successfully.",
    "token": "1|abcdef123456...",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "student"
    },
    "permissions": [
        "events.view",
        "forums.create"
    ]
}
```

#### 3. Use the Token

```http
GET /api/v1/me/stats
Authorization: Bearer 1|abcdef123456...
```

## 📋 API Endpoints Overview

### Authentication Endpoints

| Method | Endpoint                       | Description                | Auth Required |
|--------|--------------------------------|----------------------------|---------------|
| POST   | `/api/v1/auth/register`        | Register a new user        | No            |
| POST   | `/api/v1/auth/login`           | Login and get access token | No            |
| POST   | `/api/v1/auth/logout`          | Logout and revoke tokens   | Yes           |
| POST   | `/api/v1/auth/forgot-password` | Request password reset     | No            |
| POST   | `/api/v1/auth/reset-password`  | Reset password with token  | No            |
| PUT    | `/api/v1/auth/change-password` | Change password            | Yes           |
| GET    | `/api/v1/auth/profile`         | Get user profile           | Yes           |
| PUT    | `/api/v1/auth/profile`         | Update user profile        | Yes           |

### Events Endpoints

| Method | Endpoint                  | Description             | Auth Required       |
|--------|---------------------------|-------------------------|---------------------|
| GET    | `/api/v1/events`          | List all events         | No                  |
| GET    | `/api/v1/events/upcoming` | List upcoming events    | No                  |
| GET    | `/api/v1/events/{id}`     | Get event details       | No                  |
| POST   | `/api/v1/events`          | Create new event        | Yes (Admin/Creator) |
| PUT    | `/api/v1/events/{id}`     | Update event            | Yes (Admin/Creator) |
| DELETE | `/api/v1/events/{id}`     | Delete event            | Yes (Admin/Creator) |
| GET    | `/api/v1/admin/events`    | List all events (admin) | Yes (Admin)         |

### Event Registration Endpoints

| Method | Endpoint                                     | Description                     | Auth Required     |
|--------|----------------------------------------------|---------------------------------|-------------------|
| POST   | `/api/v1/events/{event}/register`            | Register for event (individual) | Yes               |
| POST   | `/api/v1/events/{event}/register-group`      | Register group for event        | Yes (Company Rep) |
| GET    | `/api/v1/events/{event}/registration-status` | Check registration status       | Yes               |
| GET    | `/api/v1/my-registrations`                   | Get user's registrations        | Yes               |
| GET    | `/api/v1/registrations/{id}`                 | Get registration details        | Yes               |
| POST   | `/api/v1/registrations/{id}/cancel`          | Cancel registration             | Yes               |
| GET    | `/api/v1/events/{event}/registrations`       | List event registrations        | Yes (Admin)       |
| GET    | `/api/v1/events/{event}/analytics`           | Get event analytics             | Yes (Admin)       |

### Payment Endpoints

| Method | Endpoint                       | Description           | Auth Required |
|--------|--------------------------------|-----------------------|---------------|
| GET    | `/api/v1/payment-gateways`     | List payment gateways | No            |
| POST   | `/api/v1/payments/initiate`    | Initiate payment      | Yes           |
| GET    | `/api/v1/payments/{id}`        | Get payment details   | Yes           |
| GET    | `/api/v1/payments/{id}/status` | Check payment status  | Yes           |
| POST   | `/api/v1/payments/{id}/verify` | Verify payment        | Yes           |
| GET    | `/api/v1/my-payments`          | Get user's payments   | Yes           |
| POST   | `/api/v1/payments/{id}/refund` | Refund payment        | Yes (Admin)   |

### Messaging Endpoints

| Method | Endpoint                              | Description               | Auth Required |
|--------|---------------------------------------|---------------------------|---------------|
| GET    | `/api/v1/threads`                     | List message threads      | Yes           |
| POST   | `/api/v1/threads`                     | Create new thread         | Yes           |
| GET    | `/api/v1/threads/{id}`                | Get thread details        | Yes           |
| PUT    | `/api/v1/threads/{id}`                | Update thread             | Yes           |
| DELETE | `/api/v1/threads/{id}`                | Delete thread             | Yes           |
| POST   | `/api/v1/threads/{id}/members`        | Add member to thread      | Yes           |
| DELETE | `/api/v1/threads/{id}/members`        | Remove member from thread | Yes           |
| POST   | `/api/v1/threads/{id}/leave`          | Leave thread              | Yes           |
| GET    | `/api/v1/threads/{threadId}/messages` | List messages in thread   | Yes           |
| POST   | `/api/v1/threads/{threadId}/messages` | Send message              | Yes           |

### Forums Endpoints

| Method | Endpoint                         | Description        | Auth Required |
|--------|----------------------------------|--------------------|---------------|
| GET    | `/api/v1/forums`                 | List all forums    | Yes           |
| POST   | `/api/v1/forums`                 | Create new forum   | Yes           |
| GET    | `/api/v1/forums/{id}`            | Get forum details  | Yes           |
| PUT    | `/api/v1/forums/{id}`            | Update forum       | Yes           |
| DELETE | `/api/v1/forums/{id}`            | Delete forum       | Yes           |
| POST   | `/api/v1/forums/{id}/join`       | Join forum         | Yes           |
| POST   | `/api/v1/forums/{id}/leave`      | Leave forum        | Yes           |
| GET    | `/api/v1/forums/{id}/members`    | List forum members | Yes           |
| GET    | `/api/v1/forums/{forumId}/posts` | List forum posts   | Yes           |
| POST   | `/api/v1/forums/{forumId}/posts` | Create new post    | Yes           |

### Articles Endpoints

| Method | Endpoint                         | Description                 | Auth Required |
|--------|----------------------------------|-----------------------------|---------------|
| GET    | `/api/v1/articles`               | List all articles           | No            |
| GET    | `/api/v1/articles/trending`      | Get trending articles       | No            |
| GET    | `/api/v1/articles/{id}`          | Get article details         | No            |
| POST   | `/api/v1/articles`               | Create new article          | Yes           |
| PUT    | `/api/v1/articles/{id}`          | Update article              | Yes           |
| DELETE | `/api/v1/articles/{id}`          | Delete article              | Yes           |
| POST   | `/api/v1/articles/{id}/like`     | Like/unlike article         | Yes           |
| POST   | `/api/v1/articles/{id}/bookmark` | Bookmark/unbookmark article | Yes           |
| GET    | `/api/v1/articles/{id}/comments` | List article comments       | No            |
| POST   | `/api/v1/articles/{id}/comments` | Add comment to article      | Yes           |

### Donation Endpoints

| Method | Endpoint                       | Description                 | Auth Required |
|--------|--------------------------------|-----------------------------|---------------|
| GET    | `/api/v1/campaigns`            | List donation campaigns     | No            |
| GET    | `/api/v1/campaigns/{id}`       | Get campaign details        | No            |
| POST   | `/api/v1/campaigns`            | Create new campaign         | Yes           |
| PUT    | `/api/v1/campaigns/{id}`       | Update campaign             | Yes           |
| DELETE | `/api/v1/campaigns/{id}`       | Delete campaign             | Yes           |
| GET    | `/api/v1/donations`            | List donations              | No            |
| POST   | `/api/v1/donations`            | Make a donation             | Yes           |
| GET    | `/api/v1/donations/my/history` | Get user's donation history | Yes           |

### Admin Endpoints

| Method | Endpoint                                 | Description                | Auth Required |
|--------|------------------------------------------|----------------------------|---------------|
| GET    | `/api/v1/admin/pending-verifications`    | List pending verifications | Yes (Admin)   |
| POST   | `/api/v1/admin/verify-user/{userId}`     | Approve user verification  | Yes (Admin)   |
| POST   | `/api/v1/admin/reject-user/{userId}`     | Reject user verification   | Yes (Admin)   |
| GET    | `/api/v1/admin/users`                    | List all users             | Yes (Admin)   |
| PUT    | `/api/v1/admin/users/{userId}/role`      | Update user role           | Yes (Admin)   |
| PATCH  | `/api/v1/admin/users/{userId}/suspend`   | Suspend/unsuspend user     | Yes (Admin)   |
| GET    | `/api/v1/admin/roles`                    | List all roles             | Yes (Admin)   |
| GET    | `/api/v1/admin/permissions`              | List all permissions       | Yes (Admin)   |
| POST   | `/api/v1/admin/roles/{role}/permissions` | Update role permissions    | Yes (Admin)   |

### Meta Endpoints

| Method | Endpoint       | Description                  | Auth Required |
|--------|----------------|------------------------------|---------------|
| GET    | `/api/v1/meta` | Get API metadata and version | No            |

## 🚀 Quick Start Examples

### Example 1: Get Upcoming Events

```bash
curl -X GET "http://localhost:8000/api/v1/events/upcoming" \
  -H "Accept: application/json"
```

### Example 2: Register for an Event

```bash
curl -X POST "http://localhost:8000/api/v1/events/1/register" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "attendee_name": "John Doe",
    "attendee_email": "john@example.com"
  }'
```

### Example 3: Create a Forum Post

```bash
curl -X POST "http://localhost:8000/api/v1/forums/1/posts" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "My First Post",
    "content": "This is the content of my post"
  }'
```

## 📝 Response Format

All API responses follow a consistent JSON format:

### Success Response

```json
{
    "message": "Operation successful",
    "data": {
        // Response data here
    }
}
```

### Error Response

```json
{
    "message": "Error message here",
    "errors": {
        "field_name": [
            "Validation error message"
        ]
    }
}
```

## 🔢 HTTP Status Codes

| Code | Meaning                                               |
|------|-------------------------------------------------------|
| 200  | OK - Request successful                               |
| 201  | Created - Resource created successfully               |
| 204  | No Content - Request successful, no content to return |
| 400  | Bad Request - Invalid request format                  |
| 401  | Unauthorized - Authentication required or failed      |
| 403  | Forbidden - Authenticated but not authorized          |
| 404  | Not Found - Resource not found                        |
| 422  | Unprocessable Entity - Validation failed              |
| 500  | Internal Server Error - Server error                  |

## 🔄 Pagination

List endpoints support pagination with the following query parameters:

- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 15, max: 100)

Example:

```
GET /api/v1/events?page=2&per_page=20
```

Response includes pagination metadata:

```json
{
    "data": [
        ...
    ],
    "meta": {
        "current_page": 2,
        "last_page": 5,
        "per_page": 20,
        "total": 95
    }
}
```

## 🔍 Filtering and Sorting

Many endpoints support filtering and sorting:

### Filtering

```
GET /api/v1/events?status=upcoming&category=technology
```

### Sorting

```
GET /api/v1/events?sort_by=created_at&sort_order=desc
```

## 🛠️ Development Tools

### Testing with Postman

1. Import the Postman collection from `postman_collection.json`
2. Set the `base_url` variable to `http://localhost:8000`
3. After login, set the `token` variable with your access token

### Testing with cURL

See the examples above for cURL usage patterns.

## 📞 Support

For API support and questions:

- Email: support@sahwi-career-expo.com
- Documentation: http://localhost:8000/api/documentation

## 📄 License

Proprietary - SAHWI Career Expo Platform

---

**Last Updated:** February 2026
**API Version:** v1.0.0
