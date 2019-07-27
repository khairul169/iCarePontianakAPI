# API Endpoint

## Auth
**POST** `auth/register`
- username: **string**
- password: **string**

**POST** `auth/login`
- username: **string**
- password: **string**

**GET** `auth/validate`

## Users
**GET** `users/`

**GET** `users/{id}`

**PATCH** `users/`
- key: **string**
- value: **any**
