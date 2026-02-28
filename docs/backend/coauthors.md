The co-author system allows a story to have one original author and multiple invited collaborators.

Its purpose is to enable collaboration without changing post ownership. The original author (WordPress `post_author`) always remains the owner of the story.

When a user writes a story, they can invite other users to collaborate while retaining full ownership of the post.

---

## Core Structure

Every story has:

* One original author (`post_author`)
* Zero to five co-authors

Co-authors are stored separately from the WordPress post author and include a status that determines their level of participation.

Co-author statuses:

* `pending`
* `accepted`
* `refused`

Only users with `accepted` status are treated as collaborators.

---

## Co-Author Limit

Each story may have up to **5** co-authors at a time.

The limit includes users with:

* `pending`
* `accepted`

Users with `refused` status do not count toward the limit.

---

## Who Can Manage Co-Authors

When the feature is enabled, co-authors can be managed by:

* The original author
* Any accepted co-author

Accepted co-authors can invite new collaborators and remove existing ones.

---

## Invitation Validation Rules

When a user attempts to invite someone as a co-author, the system blocks the action if:

* The feature is disabled
* The story does not exist
* The invited user does not exist
* The inviter attempts to invite themselves
* The invited user is the original author
* The invited user already has a pending invitation
* The invited user is already accepted
* The invited user previously refused
* The story has reached the co-author limit

Only valid invitations are stored.

---

## After An Invitation Is Created

When an invitation is sent:

* The invited user is stored as `pending`
* The invited user receives an invitation notification
* The invitation remains active until accepted, refused, or removed

Pending invitations remain visible in notifications until resolved.

---

## Accepting Or Refusing An Invitation

When a pending user accepts:

* Status changes to `accepted`
* Pending invitation notification is removed
* Both parties receive confirmation notifications
* The story search index is refreshed

When a pending user refuses:

* Status changes to `refused`
* Pending invitation notification is removed
* Both parties receive refusal notifications

Refused users remain stored but do not count toward the co-author limit.

---

## Permissions After Acceptance

Accepted co-authors can:

* Edit story metadata
* Edit chapters
* Manage co-authors

They are treated as collaborators by capability checks.

The original author remains the WordPress post owner.

---

## Pending Invitation Access

Users with `pending` status:

* Do not receive editing permissions
* Do receive read-only preview access to the story and its chapters (including drafts)

This allows invited users to review the content before deciding.

---

## Removing A Co-Author

A user with permission to manage co-authors can remove:

* Accepted co-authors
* Pending invitations

The original author cannot be removed.

If an accepted co-author is removed:

* A removal notification is sent
* The story search index is refreshed

If a pending invitation is removed:

* The invitation notification is deleted

---

## Feature Toggle Behavior

The co-author feature can be disabled via settings.

When disabled:

* Co-author management stops functioning
* Existing co-author data remains stored
* Users are notified of the change
* Affected stories are reindexed

When re-enabled:

* Preserved co-author relationships become active again
* Users are notified
* Stories are reindexed

Disabling the feature does not delete stored data.

---

## Search And Display Impact

Only accepted co-authors affect public display and indexing.

They are used for:

* Author display on story pages
* Co-authored listings on profile pages
* Search index author references

Pending and refused users are not publicly displayed.

---

## Story Form Behavior

When the feature is enabled, the story form includes a co-author picker.

A user can:

* Search for users
* Add them to the form
* Remove them from the form

Upon saving the story, the system compares the selected users to the database state and:

* Sends new invitations
* Removes users no longer selected

The story form acts as the source of truth for current co-author relationships.

---

## Story Deletion

When a story is deleted, all associated co-author relationships are automatically removed.

---

## Reader-Facing Behavior

From a reader’s perspective:

* Stories may display multiple authors
* Accepted collaborators appear as real co-authors
* User profiles can list co-authored stories

Invitation workflows, permissions, and validation occur in the background to maintain controlled collaboration.

---

If you want, we can also tighten this further. Right now it’s clean, but we could compress it by ~20% without losing clarity.
