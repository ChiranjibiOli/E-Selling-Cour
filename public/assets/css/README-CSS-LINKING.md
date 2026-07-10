# Clean CSS Structure for Course Selling Platform

This ZIP contains plain CSS files only. There is no PowerShell wrapper, no `@'`, and no `Set-Content`.

## Common CSS links for `app/views/layouts/header.php`

```php
<link rel="stylesheet" href="assets/css/base/reset.css?v=1">
<link rel="stylesheet" href="assets/css/base/variables.css?v=1">
<link rel="stylesheet" href="assets/css/base/typography.css?v=1">
<link rel="stylesheet" href="assets/css/base/layout.css?v=1">

<link rel="stylesheet" href="assets/css/components/buttons.css?v=1">
<link rel="stylesheet" href="assets/css/components/forms.css?v=1">
<link rel="stylesheet" href="assets/css/components/alerts.css?v=1">
<link rel="stylesheet" href="assets/css/components/cards.css?v=1">
<link rel="stylesheet" href="assets/css/components/modals.css?v=1">
```

## Load only needed CSS per page

Landing:
```php
<link rel="stylesheet" href="assets/css/navbars/public-navbar.css?v=1">
<link rel="stylesheet" href="assets/css/components/course-card.css?v=1">
<link rel="stylesheet" href="assets/css/pages/public/landing.css?v=1">
<link rel="stylesheet" href="assets/css/components/footer.css?v=1">
```

Login/Register:
```php
<link rel="stylesheet" href="assets/css/pages/public/auth.css?v=1">
```

Student dashboard:
```php
<link rel="stylesheet" href="assets/css/navbars/student-navbar.css?v=1">
<link rel="stylesheet" href="assets/css/components/stats-card.css?v=1">
<link rel="stylesheet" href="assets/css/components/course-card.css?v=1">
<link rel="stylesheet" href="assets/css/pages/student/dashboard.css?v=1">
```

Student browse courses:
```php
<link rel="stylesheet" href="assets/css/navbars/student-navbar.css?v=1">
<link rel="stylesheet" href="assets/css/components/filter-bar.css?v=1">
<link rel="stylesheet" href="assets/css/components/course-card.css?v=1">
<link rel="stylesheet" href="assets/css/pages/student/browse-courses.css?v=1">
```

Instructor my courses:
```php
<link rel="stylesheet" href="assets/css/navbars/instructor-navbar.css?v=1">
<link rel="stylesheet" href="assets/css/components/course-card.css?v=1">
<link rel="stylesheet" href="assets/css/pages/instructor/my-courses.css?v=1">
```

Admin courses:
```php
<link rel="stylesheet" href="assets/css/navbars/admin-navbar.css?v=1">
<link rel="stylesheet" href="assets/css/components/filter-bar.css?v=1">
<link rel="stylesheet" href="assets/css/components/tables.css?v=1">
<link rel="stylesheet" href="assets/css/components/course-card.css?v=1">
<link rel="stylesheet" href="assets/css/pages/admin/courses.css?v=1">
```

Do not paste PowerShell commands into CSS files. CSS files should contain CSS only.
