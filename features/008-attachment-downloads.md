# Attachment Downloads

- Branch: `feature/008-attachment-downloads`
- Related Issue: —
- **Status**: 🟡 In Progress

## Overview

Shell's mail proxy can already retrieve messages from IMAP accounts and expose them via the API, including surfacing `has_attachments` and `attachment_count` on each message. However, there is no way for API consumers to list the individual attachments on a message or download their contents. Agents and clients that need to process email attachments (e.g. invoices, reports, images) have no path forward.

This feature adds two new API endpoints: one to list attachment metadata for a message, and one to stream a single attachment as a binary download. Both sit under the existing mail route group, use the same Sanctum + account-owner middleware, and follow the established responder pattern.

### Background / Current Behavior

- **Current behavior**: `MessageResource` exposes `has_attachments: true` and `attachment_count: 2` but provides no further detail on individual attachments.
- **Problem**: Clients cannot retrieve attachment filenames, MIME types, or binary content.
- **Where it happens**: `app/Http/Resources/MessageResource.php`, `resources/docs/mailboxes.md`
- **Impact**: Any consumer (agent, frontend) that needs to work with email attachments is blocked.

### Target Outcome

This feature adds two new API endpoints so that API consumers can:
- List all attachments on a message with their metadata (filename, MIME type, extension, content-ID)
- Download a specific attachment by its index as a binary streamed file

## Requirements

### Functional

- [ ] `GET /api/{account}/mailbox/{folder}/messages/{uid}/attachments` returns a JSON array of attachment metadata
- [ ] `GET /api/{account}/mailbox/{folder}/messages/{uid}/attachments/{index}` streams the attachment binary with correct `Content-Type` and `Content-Disposition: attachment` headers
- [ ] Both endpoints are protected by `auth:sanctum` and `account.owner` middleware
- [ ] A missing or out-of-bounds `{index}` returns a 404
- [ ] `resources/docs/mailboxes.md` documents both new endpoints

### Non-Functional

- [ ] Security/Permissions: same Sanctum + `account.owner` + `service.connected:mail` gates as existing mail routes — no new roles needed
- [ ] Backwards compatibility: no changes to existing `MessageResource` shape

### Out of Scope

- Caching or persisting attachment content to disk
- Inline (embedded image) rendering
- Part-number-based addressing (using `BodyStructurePart::partNumber()`) — index-based is sufficient and simpler

## Implementation Plan

### Phase 1: AttachmentResource + List Attachments Endpoint

**Files to create/modify:**
- `app/Http/Resources/AttachmentResource.php` *(new)*
- `app/Http/Responders/Api/ListAttachmentsResponder.php` *(new)*
- `routes/api.php` *(add route)*
- `tests/Feature/Api/AttachmentApiTest.php` *(new)*

**Steps:**

1. Create `app/Http/Resources/AttachmentResource.php`:
   - Extends `JsonResource`; wraps a `DirectoryTree\ImapEngine\Attachment` object
   - `toArray()` returns: `index` (must be passed via `additional()`/`with()` or use collection wrapping with keys), `filename`, `content_type`, `content_id`, `extension`
   - **Do NOT** include `contents` — that is binary data
   - Check sibling resources (`MessageResource`, `FolderResource`) for exact conventions

2. Create `app/Http/Responders/Api/ListAttachmentsResponder.php`:
   - Constructor-injected `MailboxService`
   - `__invoke(Request $request, Account $account, string $folder, int $uid)` signature
   - Calls `$this->mailboxService->connect($account)`, `getFolder($folder)` (abort 404 if null), `getMessage($folder, $uid)` (abort 404 if null)
   - Calls `$message->attachments()` and wraps in `AttachmentResource::collection()`
   - `try/catch(\Throwable $e)` — re-throw `HttpException`, abort 503 otherwise
   - Return type: `AnonymousResourceCollection`
   - The `index` of each attachment needs to be conveyed to the resource. Practical approach: manually build the collection by iterating `$attachments` with `array_map` or `collect()->map()`, passing each `Attachment` to `new AttachmentResource($attachment)` with an `additional(['index' => $i])`, or more simply: iterate and set a custom property on the resource. Check how the existing collection resources handle extra data, or just map to an array of `AttachmentResource` instances and call `->additional(['index' => ...])` on each. An alternative is to not put `index` in `AttachmentResource` and instead return a plain array — but check existing resource conventions first.

3. Add route in `routes/api.php` inside the `service.connected:mail` middleware group:
   ```php
   Route::get('/{account}/mailbox/{folder}/messages/{uid}/attachments', ListAttachmentsResponder::class);
   ```

4. Create `tests/Feature/Api/AttachmentApiTest.php`:
   - Mirror `MessageApiTest.php` structure (`RefreshDatabase`, User/Account factories, Sanctum token)
   - Mock `MailboxService` via `$this->mock(MailboxService::class, ...)`
   - Use `FakeMessage` with a valid multipart MIME body as `contents:` to generate real `Attachment` objects via `HasParsedMessage`
   - Write a helper `makeMultipartMessage(string $filename, string $content, string $mimeType): string` that builds a minimal RFC 2822 multipart body for test use
   - Scenarios to cover:
     - Successful list returns correct attachment metadata
     - 404 when folder not found
     - 404 when message (uid) not found
     - 404 when accessing another user's account
     - 404 when `mail_used` is false on the account
     - 401 when unauthenticated
   - Run: `php artisan test --compact --filter=AttachmentApiTest`

5. Run `vendor/bin/pint --dirty --format agent`

---

### Phase 2: Download Attachment Endpoint

**Files to create/modify:**
- `app/Http/Responders/Api/DownloadAttachmentResponder.php` *(new)*
- `routes/api.php` *(add route)*
- `tests/Feature/Api/AttachmentApiTest.php` *(extend)*

**Steps:**

1. Create `app/Http/Responders/Api/DownloadAttachmentResponder.php`:
   - Constructor-injected `MailboxService`
   - `__invoke(Request $request, Account $account, string $folder, int $uid, int $index)` signature
   - Calls the same connect/getFolder/getMessage chain as `ListAttachmentsResponder`
   - After getting the message: `$attachments = $message->attachments()` — `abort_unless(isset($attachments[$index]), 404)` to handle out-of-bounds
   - `$attachment = $attachments[$index]`
   - Returns `response()->streamDownload()`:
     ```php
     return response()->streamDownload(
         callback: fn () => print $attachment->contents(),
         name: $attachment->filename() ?? "attachment-{$index}",
         headers: ['Content-Type' => $attachment->contentType()],
         disposition: 'attachment',
     );
     ```
   - Return type: `\Symfony\Component\HttpFoundation\StreamedResponse`
   - `try/catch(\Throwable $e)` as usual

2. Add route in `routes/api.php` inside the `service.connected:mail` middleware group:
   ```php
   Route::get('/{account}/mailbox/{folder}/messages/{uid}/attachments/{index}', DownloadAttachmentResponder::class);
   ```
   This route must be registered **after** the list-attachments route to avoid `{index}` matching "attachments".

3. Extend `tests/Feature/Api/AttachmentApiTest.php` with download scenarios:
   - Successful download: assert `->assertDownload('invoice.pdf')` and `->assertStreamedContent($binaryContent)`
   - 404 when index is out of bounds
   - 404 for folder not found, uid not found, other user's account, `mail_used` false
   - 401 when unauthenticated

4. Run `vendor/bin/pint --dirty --format agent`

---

### Phase 3: Documentation Update

**Files to modify:**
- `resources/docs/mailboxes.md`

**Steps:**

1. Read `resources/docs/mailboxes.md` to confirm current section structure and style.

2. Add a section for `GET /api/{account}/mailbox/{folder}/messages/{uid}/attachments`:
   - Parameter table: `account` (Account ID), `folder` (URL-encoded folder name), `uid` (message UID)
   - curl example using `Authorization: Bearer {token}`
   - Success response example (JSON array with `index`, `filename`, `content_type`, `extension`, `content_id`)
   - Errors table: 401, 404 (account/folder/message not found), 503

3. Add a section for `GET /api/{account}/mailbox/{folder}/messages/{uid}/attachments/{index}`:
   - Parameter table: same as above + `index` (0-based attachment index)
   - curl example with `-o filename.pdf` to save the binary
   - Note: response is a binary stream, not JSON; `Content-Type` matches the attachment's MIME type
   - Errors table: 401, 404 (including out-of-bounds index), 503

4. Update the show-message response example to note that `attachment_count` indicates how many attachments are available and point to the attachments endpoint.

---

## Progress

### Completed
- Phase 1: AttachmentResource + ListAttachmentsResponder + route + tests

### In Progress
- —

### Blocked
- —

### To Do
- Phase 2: DownloadAttachmentResponder + route + tests
- Phase 3: Docs update

## Technical Notes

- `Attachment::toArray()` includes raw binary `contents` — never delegate to it in `AttachmentResource`. Build the array manually.
- Attachment identifier is **index** (0-based position in `$message->attachments()`). There is no stable part number on the parsed `Attachment` object. `BodyStructurePart::partNumber()` exists but requires a separate `bodyStructure()` call and adds complexity. Index is deterministic per IMAP fetch.
- `Attachment::contents()` calls a PSR-7 stream's `getContents()` — one-shot read. Fine for a download response where the stream is written once.
- `FakeMessage` in tests uses `HasParsedMessage` which calls `MessageParser::parse()` on the raw `contents:` string. A valid RFC 2822 multipart MIME body (with a `Content-Disposition: attachment` part) is sufficient to get real `Attachment` objects in tests without any mocking of `Attachment` itself.

## Files Modified/Created

### API / Controllers / Responders
- `app/Http/Responders/Api/ListAttachmentsResponder.php` *(new)*
- `app/Http/Responders/Api/DownloadAttachmentResponder.php` *(new)*
- `app/Http/Resources/AttachmentResource.php` *(new)*
- `routes/api.php` *(add two routes)*

### Views / Frontend
- `resources/docs/mailboxes.md` *(document new endpoints)*

### Tests
- `tests/Feature/Api/AttachmentApiTest.php` *(new)*

## Testing
🟡 **Pending**: No tests written yet.

## Questions/Decisions Needed
- [ ] Should `AttachmentResource` include a `size` field? `Attachment` does not expose size directly (only `contents()` which reads the full binary). Probably out of scope for now.

## Decisions Made
- Attachment identifier = 0-based index (not filename, not content-ID, not IMAP part number)
- No storage/caching of attachment content — streamed live from IMAP on each request

## Pre-Commit Checklist
Before every commit, ensure the following have been completed:
- [ ] Code formatting applied (`composer format`)
- [ ] All tests passing (`composer test`)
- [ ] Feature doc updated (progress, files modified, session history)
- **Never commit unless explicitly asked to by the user.**

## Session History

### Session 1
- Feature branch and worktree created

### Session 2
- Researched IMAP attachment API, existing responder/resource/route/test patterns
- Wrote phased implementation plan
