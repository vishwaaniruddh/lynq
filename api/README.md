# ADV CRM Users Module - API Documentation

## Overview

This API provides RESTful endpoints for user management operations in the ADV CRM system. All endpoints enforce company isolation, permission checking, and rate limiting.

## Authentication

The API supports two authentication methods:

### 1. Session-Based Authentication
Use standard PHP session cookies after logging in via `/api/auth/login.php`.

### 2. Bearer Token Authentication
Include the session token in the Authorization header:
```
Authorization: Bearer <session_token>
```

## Common Response Format

### Success Response
```json
{
    "success": true,
    "message": "Operation successful",
    "data": { ... },
    "timestamp": "2024-12-28T10:30:00+00:00"
}
```

### Error Response
```json
{
    "success": false,
    "error": {
        "code": "ERROR_CODE",
        "message": "Human readable message",
        "details": { ... }
    },
    "timestamp": "2024-12-28T10:30:00+00:00"
}
```

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| UNAUTHORIZED | 401 | Authentication required |
| PERMISSION_DENIED | 403 | Insufficient permissions |
| NOT_FOUND | 404 | Resource not found |
| METHOD_NOT_ALLOWED | 405 | HTTP method not supported |
| VALIDATION_ERROR | 400 | Input validation failed |
| RATE_LIMIT_EXCEEDED | 429 | Too many requests |
| SERVER_ERROR | 500 | Internal server error |

## Rate Limiting

- **Limit**: 100 requests per hour per IP/user
- **Headers**: 
  - `X-RateLimit-Limit`: Maximum requests allowed
  - `X-RateLimit-Remaining`: Requests remaining
  - `X-RateLimit-Reset`: Unix timestamp when limit resets

## Security Headers

All responses include:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Cache-Control: no-store, no-cache, must-revalidate`

---

## Authentication Endpoints

### POST /api/auth/login.php

Authenticate user and create session.

**Request:**
```json
{
    "username": "string",
    "password": "string"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "username": "admin",
            "email": "admin@example.com",
            "role": "Super Admin",
            "company": "ADV Company",
            "company_type": "ADV"
        },
        "redirect": "../../dashboard.php"
    }
}
```

---

## User Endpoints

### GET /api/users/index.php

List users with company isolation.

**Permission Required:** `users.read`

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| company_id | int | Filter by company (ADV only) |
| search | string | Search username/email |
| page | int | Page number (default: 1) |
| limit | int | Results per page (default: 20, max: 100) |

**Response:**
```json
{
    "success": true,
    "data": {
        "users": [
            {
                "id": 1,
                "username": "admin",
                "email": "admin@example.com",
                "first_name": "Admin",
                "last_name": "User",
                "company_id": 1,
                "company_name": "ADV Company",
                "company_type": "ADV",
                "role_id": 1,
                "role_name": "Super Admin",
                "status": 1
            }
        ],
        "pagination": {
            "page": 1,
            "limit": 20,
            "total": 50,
            "total_pages": 3
        }
    }
}
```

### GET /api/users/show.php

Get single user details.

**Permission Required:** `users.read`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | int | Yes | User ID |

**Response:**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "username": "admin",
            "email": "admin@example.com",
            ...
        }
    }
}
```

### POST /api/users/create.php

Create a new user.

**Permission Required:** `users.create`

**Request Body:**
```json
{
    "username": "newuser",
    "email": "newuser@example.com",
    "password": "SecurePass123!",
    "first_name": "New",
    "last_name": "User",
    "company_id": 1,
    "role_id": 2
}
```

**Validation Rules:**
- `username`: Required, unique
- `email`: Required, valid email format, unique
- `password`: Required, min 8 chars, must contain uppercase, lowercase, number, special char
- `company_id`: Required, must have access
- `role_id`: Required, must match company type

**Response (201):**
```json
{
    "success": true,
    "message": "User created successfully",
    "data": {
        "user": { ... }
    }
}
```

### PUT /api/users/update.php

Update an existing user.

**Permission Required:** `users.update`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | int | Yes | User ID |

**Request Body:**
```json
{
    "email": "updated@example.com",
    "first_name": "Updated",
    "last_name": "Name",
    "role_id": 3,
    "status": 1
}
```

**Notes:**
- Only include fields you want to update
- `company_id` can only be changed by ADV users
- Password changes require meeting strength requirements

### DELETE /api/users/delete.php

Delete a user.

**Permission Required:** `users.delete`

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | int | Yes | User ID |

**Notes:**
- Cannot delete your own account
- Company isolation is enforced

---

## Permission Endpoints

### GET /api/permissions/index.php

List all available permissions.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| grouped | string | If "true", group by module |

### GET /api/permissions/user.php

Get permissions for a specific user.

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | int | Yes | User ID |

### POST /api/permissions/delegate.php

Delegate permission to a contractor company.

**Permission Required:** ADV user only

**Request Body:**
```json
{
    "company_id": 2,
    "permission": "users.read"
}
```

### DELETE /api/permissions/revoke.php

Revoke delegated permission from a company.

**Permission Required:** ADV user only

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| company_id | int | Yes | Company ID |
| permission | string | Yes | Permission name |

---

## Company Isolation

- **ADV Users**: Can access all companies and users
- **Contractor Users**: Can only access their own company's data

All queries are automatically filtered based on the authenticated user's company type.

---

## Examples

### cURL Examples

**Login:**
```bash
curl -X POST http://localhost/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password123"}'
```

**List Users:**
```bash
curl -X GET "http://localhost/api/users/index.php?page=1&limit=10" \
  -H "Authorization: Bearer <token>"
```

**Create User:**
```bash
curl -X POST http://localhost/api/users/create.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{
    "username": "newuser",
    "email": "newuser@example.com",
    "password": "SecurePass123!",
    "company_id": 1,
    "role_id": 2
  }'
```

**Update User:**
```bash
curl -X PUT "http://localhost/api/users/update.php?id=5" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"first_name": "Updated", "last_name": "Name"}'
```

**Delete User:**
```bash
curl -X DELETE "http://localhost/api/users/delete.php?id=5" \
  -H "Authorization: Bearer <token>"
```
