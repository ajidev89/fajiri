# Ambassadors, Testimonies, and FAQs

Base URL: `https://api.fajiri.org/v1`

All successful responses include `status: true` and a `message`. IDs are UUIDs. Timestamps are ISO 8601.

Public `GET` routes do not need a token. Create, update, and delete need:

```http
Authorization: Bearer {token}
Accept: application/json
```

The token’s role must include the `system_settings` permission. Missing auth returns `401`. Missing permission returns `403`.

## Response shapes

A single record:

```json
{
  "status": true,
  "message": "Ambassador fetched successfully",
  "data": {}
}
```

A paginated list (ambassadors, testimonies, and the admin FAQ list):

```json
{
  "status": true,
  "message": "Ambassadors fetched successfully",
  "data": [],
  "links": {
    "first": "https://api.fajiri.org/v1/ambassadors?page=1",
    "last": "https://api.fajiri.org/v1/ambassadors?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "https://api.fajiri.org/v1/ambassadors",
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

The public FAQ list is not paginated. `data` is a flat array.

Validation errors (`422`):

```json
{
  "status": false,
  "message": "The name field is required.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

Not found (`404`):

```json
{
  "status": false,
  "message": "Ambassador not found",
  "data": null
}
```

---

## Ambassadors

Used for the “Meet Our Ambassadors” grid and the portrait dialog.

| Field | Type | Notes |
| --- | --- | --- |
| `id` | string | UUID |
| `name` | string | Card and dialog heading |
| `slug` | string | Generated from `name` on create. It does not change if the name is edited later. |
| `title` | string | Role under the name, for example `Chief Executive Officer` |
| `biography` | string | Long text in the dialog |
| `photo` | string | Absolute image URL |
| `sort_order` | integer | Lower numbers appear first |
| `created_at` | string | |
| `updated_at` | string | |

Render the grid from `photo`, `name`, and `title`. Open the dialog with `name`, `title`, `photo`, and `biography`.

### List

`GET /ambassadors`

Query parameters:

| Param | Default | Notes |
| --- | --- | --- |
| `search` | | Matches name, title, or biography |
| `sort_by` | `sort_order` | `sort_order`, `name`, `title`, `created_at`, `updated_at` |
| `sort_order` | `asc` when sorting by `sort_order`, otherwise `desc` | `asc` or `desc` |
| `per_page` | `15` | Raise this if the page should show every ambassador |
| `page` | `1` | |

Omit the sort params to get display order (`sort_order` ascending, then name).

```http
GET /v1/ambassadors?per_page=50
```

### Show

`GET /ambassadors/{slug}`

```http
GET /v1/ambassadors/frank-ajirioghene-urefe
```

### Create

`POST /ambassadors`

`Content-Type: multipart/form-data`

| Field | Rules |
| --- | --- |
| `name` | required, max 255 |
| `title` | required, max 255 |
| `biography` | required |
| `photo` | required image, jpeg, png, jpg, gif, or webp, max 2MB |
| `sort_order` | optional integer, minimum 0. Defaults to `0` |

### Update

`PUT /ambassadors/{id}`

`{id}` is the UUID, not the slug. Send the same fields as create. `photo` is optional; omit it to keep the current portrait.

File uploads must be sent as `POST` with `_method=PUT`, because a real `PUT` does not carry the file:

```http
POST /v1/ambassadors/{id}
Content-Type: multipart/form-data

_method=PUT
name=Frank Ajirioghene Urefe
title=Chief Executive Officer
biography=...
sort_order=0
photo=<file>
```

A JSON `PUT` without a new photo is fine when only text changes.

### Delete

`DELETE /ambassadors/{id}`

```json
{
  "status": true,
  "message": "Ambassador deleted successfully",
  "data": []
}
```

---

## Testimonies

Used for the “Hear Our Stories” portraits.

| Field | Type | Notes |
| --- | --- | --- |
| `id` | string | UUID |
| `name` | string | |
| `slug` | string | Generated from `name` on create. It does not change if the name is edited later. |
| `age` | integer | 1–120 |
| `age_label` | string | Ready to display, for example `15 y.o.` |
| `story` | string | Paragraph on the expanded portrait |
| `photo` | string | Absolute image URL |
| `sort_order` | integer | Lower numbers appear first. `0` is the expanded card. |
| `created_at` | string | |
| `updated_at` | string | |

The caption in the design is `{name}, ({age_label})`, for example `John Bieber, (15 y.o.)`.

### List

`GET /testimonies`

| Param | Default | Notes |
| --- | --- | --- |
| `search` | | Matches name or story |
| `sort_by` | `sort_order` | `sort_order`, `name`, `age`, `created_at`, `updated_at` |
| `sort_order` | `asc` when sorting by `sort_order`, otherwise `desc` | `asc` or `desc` |
| `per_page` | `15` | |
| `page` | `1` | |

```http
GET /v1/testimonies?per_page=50
```

Example item:

```json
{
  "id": "9f1c...",
  "name": "John Bieber",
  "slug": "john-bieber",
  "age": 15,
  "age_label": "15 y.o.",
  "story": "After facing financial challenges while pursuing his education...",
  "photo": "https://res.cloudinary.com/.../john.jpg",
  "sort_order": 0,
  "created_at": "2026-09-26T12:00:00.000000Z",
  "updated_at": "2026-09-26T12:00:00.000000Z"
}
```

### Show

`GET /testimonies/{slug}`

### Create

`POST /testimonies`

`Content-Type: multipart/form-data`

| Field | Rules |
| --- | --- |
| `name` | required, max 255 |
| `age` | required integer, 1–120 |
| `story` | required |
| `photo` | required image, jpeg, png, jpg, gif, or webp, max 2MB |
| `sort_order` | optional integer, minimum 0. Defaults to `0` |

### Update

`PUT /testimonies/{id}`

`{id}` is the UUID. `photo` is optional. To replace the photo, `POST` the form with `_method=PUT`, same as ambassadors.

### Delete

`DELETE /testimonies/{id}`

Message: `Testimony deleted successfully`.

---

## FAQs

Used for the accordion with tabs **General**, **Donations**, and **Members**.

| Field | Type | Notes |
| --- | --- | --- |
| `id` | string | UUID |
| `type` | string | `general`, `donations`, or `members` |
| `type_label` | string | `General`, `Donations`, or `Members` |
| `question` | string | Accordion title |
| `answer` | string | Accordion body |
| `sort_order` | integer | Lower numbers appear first inside a tab |
| `created_at` | string | |
| `updated_at` | string | |

### Types

`GET /faqs/types`

```json
{
  "status": true,
  "message": "FAQ types fetched successfully",
  "data": [
    { "value": "general", "label": "General" },
    { "value": "donations", "label": "Donations" },
    { "value": "members", "label": "Members" }
  ]
}
```

Use this to build the tabs. Do not hardcode labels if you can read them from here.

### Public list

`GET /faqs`

`data` is a flat array, ordered by `sort_order`, then `created_at`. There is no pagination.

| Param | Notes |
| --- | --- |
| `type` | Optional. `general`, `donations`, or `members`. Omit it to return every FAQ. |

```http
GET /v1/faqs?type=donations
```

An unknown `type` returns `422` with Laravel’s default validation body (`message` and `errors`, without `status`).

### Admin list

`GET /admin/faqs`

Same item shape, but paginated. Requires auth and `system_settings`.

| Param | Default | Notes |
| --- | --- | --- |
| `type` | | Same values as the public list |
| `search` | | Matches question or answer |
| `sort_by` | `sort_order` | `sort_order`, `question`, `type`, `created_at`, `updated_at` |
| `sort_order` | `asc` | `asc` or `desc` |
| `per_page` | `15` | |
| `page` | `1` | |

### Create

`POST /admin/faqs`

`Content-Type: application/json`

```json
{
  "type": "general",
  "question": "How do I donate?",
  "answer": "You can donate from any campaign page.",
  "sort_order": 0
}
```

| Field | Rules |
| --- | --- |
| `type` | required, one of `general`, `donations`, `members` |
| `question` | required, max 255 |
| `answer` | required |
| `sort_order` | optional integer, minimum 0 |

### Update

`PUT /admin/faqs/{id}`

JSON body with the same fields as create. All of `type`, `question`, and `answer` are required on update.

### Delete

`DELETE /admin/faqs/{id}`

Message: `FAQ deleted successfully`.
