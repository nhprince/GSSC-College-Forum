# Chat System

## Overview
Single group chat. Real-time via SSE with 3s AJAX polling fallback.
Messages stored in `messages` table. Never hard-deleted (is_deleted=1 only).

## Real-time Strategy
Primary: Server-Sent Events (SSE) via /api/chat/stream.php
- EventSource opened with ?last_id=X
- Server polls DB every 1 second for messages with id > last_id
- Sends 'message' events with JSON payload
- Sends keepalive comments to prevent timeout
- Forces reconnect after 4 minutes (client auto-reconnects)

Fallback: AJAX polling via /api/chat/messages.php?since_id=X
- Activates if EventSource not supported or SSE errors
- Polls every 3 seconds
- Same message format as SSE events

## Message Flow (sending)
1. User types and presses Enter or Send button
2. JS calls POST /api/chat/messages.php with body JSON
3. Server inserts message, returns full message object
4. JS immediately renders message in DOM (no waiting for SSE)
5. lastId updated to new message id
6. Other users receive via their SSE stream or next poll

## Message Types
- text: body field contains text, null file fields
- image: file_path points to uploads/chat/, body is null
- file: same as image but non-image extension

## Reactions
- Stored in message_reactions table
- UNIQUE KEY on (message_id, user_id, emoji) = toggle behavior
- Built in PHP with GROUP BY (no JSON_OBJECTAGG)
- 9 allowed emojis defined in chat.js showEmojiPicker()

## Admin Controls
- Toggle chat: POST /api/chat/toggle.php {enabled:bool}  admin only
- Delete message: POST /api/chat/delete.php {message_id}  own or mod+
- Clear all: POST /api/admin/clear-chat.php {confirm:true}  admin only
- When disabled: input bar hidden, disabled message shown

## Context Menu (right-click / long-press)
- Reply: sets replyTo state, shows preview bar
- React: opens emoji picker
- Delete: soft-delete (own message or moderator+)
