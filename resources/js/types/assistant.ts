import type { RichTextDocument } from './post';

export type AssistantChangeType =
    | 'body'
    | 'title'
    | 'excerpt'
    | 'seo_title'
    | 'meta_description'
    | 'og_title'
    | 'og_description'
    | 'tags'
    | 'featured_image_alt'
    | 'featured_image_caption';

type AssistantChangeStatus =
    'proposed' | 'accepted' | 'rejected' | 'superseded' | 'stale';

export type AssistantStatus =
    'disabled' | 'unavailable' | 'idle' | 'running' | 'cancelling' | 'cooldown';

export type AssistantErrorData = {
    code: string;
    message: string;
    retry_at?: string | null;
    turn_id?: number | null;
};

export type AssistantFieldPayload = {
    old: string | string[] | null;
    new: string | string[] | null;
};

export type AssistantBodyPayload = {
    old_blocks: string;
    new_blocks: string;
    anchor_before: string | null;
    anchor_after: string | null;
};

export type AssistantChangeData = {
    id: number;
    type: AssistantChangeType;
    status: AssistantChangeStatus;
    payload: AssistantFieldPayload | AssistantBodyPayload;
    turn_id: number;
};

type AssistantTurnData = {
    id: number;
    status: string;
    assistant_message: string | null;
    duration_ms: number | null;
};

export type AssistantStreamFrame =
    | { event: 'started'; data: { turn_id: number } }
    | { event: 'heartbeat'; data: { turn_id: number } }
    | { event: 'narration'; data: { turn_id: number; text: string } }
    | {
          event: 'activity';
          data: { turn_id: number; tool: string; target: string | null };
      }
    | {
          event: 'change';
          data: { turn_id: number; change: AssistantChangeData };
      }
    | {
          event: 'done';
          data: { turn_id: number; turn: AssistantTurnData };
      }
    | {
          event: 'error';
          data: {
              turn_id: number;
              code: string;
              message: string;
              retry_at?: string | null;
          };
      };

export type AssistantTurnSummary = {
    id: number;
    task_type: string;
    status: string;
    user_prompt: string;
    assistant_message: string | null;
    error: string | null;
    context: Record<string, unknown> | null;
    created_at: string;
};

export type AssistantStateResponse = {
    enabled: boolean;
    available: boolean;
    status: AssistantStatus;
    retry_at: string | null;
    active_turn: {
        id: number;
        task_type: string;
        started_at: string | null;
    } | null;
    outline_ready: boolean;
    changes: AssistantChangeData[];
    turns: AssistantTurnSummary[];
};

export type AssistantChatEntry = {
    key: string;
    role: 'user' | 'assistant';
    text: string;
    state: 'streaming' | 'done' | 'error';
    activity: string | null;
};

export type AcceptChangeResponse = {
    change: AssistantChangeData;
    post: {
        lock_version: number;
        slug: string;
        updated_at: string;
        last_editor: string | null;
        title: string | null;
        slug_is_manual: boolean;
        excerpt: string | null;
        category_id: number | null;
        author_id: number | null;
        seo_title: string | null;
        meta_description: string | null;
        canonical_url: string | null;
        og_title: string | null;
        og_description: string | null;
        noindex: boolean;
        featured_image_alt: string | null;
        featured_image_caption: string | null;
        tags: string[];
        body: RichTextDocument | null;
    };
};
