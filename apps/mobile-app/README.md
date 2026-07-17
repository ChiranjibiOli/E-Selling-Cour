# CourseHub mobile app

This folder owns the future Android and iOS client. The mobile application must consume the same versioned API gateway as the web portals and must not duplicate server-side pricing, payment, enrollment or authorization logic.

## Planned first release

- Student registration and login
- Course discovery and details
- Cart and checkout
- Payment status
- Purchased courses
- Lesson playback
- Progress tracking
- Notifications
- Student profile

Instructor and administrator features remain web-first until the student mobile workflow is stable.

## Technology decision

The implementation framework has not been selected yet. Flutter and React Native remain candidates. The API contract is stored in `packages/api-contracts/openapi.yaml`.
