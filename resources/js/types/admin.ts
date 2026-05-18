export type TaskStatus = 'pending' | 'completed' | 'cancelled' | 'processing';
export type TaskCategory = 'money' | 'homework' | 'item' | 'event' | 'other';
export type ReminderType = 'preparation' | 'action' | 'final';
export type Assignee = 'father' | 'mother' | 'both' | 'unassigned' | string | null;

export interface AdminReminder {
    id: number;
    scheduled_at: string | null;
    type: ReminderType;
    sent: boolean;
    sent_at: string | null;
    text: string;
}

export interface AdminTask {
    id: number;
    description: string;
    category: TaskCategory;
    status: TaskStatus;
    due_date: string | null;
    due_time: string | null;
    amount: number | string | null;
    currency: string | null;
    assigned_to: Assignee;
    reminders: AdminReminder[];
}

export interface AdminMessageDetail {
    id: number;
    ref: string;
    telegram_chat_id: string;
    original_turkish: string;
    english: string | null;
    spanish: string | null;
    summary: string | null;
    processed_at: string | null;
    created_at: string | null;
    tasks: AdminTask[];
}

export interface AdminTaskRow {
    id: number;
    ref: string;
    description: string;
    category: TaskCategory;
    status: TaskStatus;
    due_date: string | null;
    due_time: string | null;
    amount: number | string | null;
    currency: string | null;
    assigned_to: Assignee;
    reminders_count: number;
    message_id: number | null;
    message_summary: string | null;
}

export interface AdminTaskDetail {
    id: number;
    ref: string;
    description: string;
    category: TaskCategory;
    status: TaskStatus;
    due_date: string | null;
    due_time: string | null;
    amount: number | string | null;
    currency: string | null;
    assigned_to: Assignee;
    completed_at: string | null;
    created_at: string | null;
    telegram_chat_id: string;
    message: { id: number; ref: string; summary: string | null } | null;
    reminders: AdminReminder[];
}

export interface AdminTaskFilters {
    status: TaskStatus | null;
    category: TaskCategory | null;
}

export interface AdminReminderRow {
    id: number;
    ref: string;
    scheduled_at: string | null;
    type: ReminderType;
    sent: boolean;
    sent_at: string | null;
    text: string;
    task: {
        id: number;
        ref: string;
        description: string;
        category: TaskCategory;
        status: TaskStatus;
    } | null;
}

export interface AdminReminderDetail {
    id: number;
    ref: string;
    scheduled_at: string | null;
    type: ReminderType;
    sent: boolean;
    sent_at: string | null;
    text: string;
    created_at: string | null;
    task: {
        id: number;
        ref: string;
        description: string;
        category: TaskCategory;
        status: TaskStatus;
        due_date: string | null;
        message: { id: number; ref: string; summary: string | null } | null;
    } | null;
}

export interface AdminReminderFilters {
    sent: 'sent' | 'pending' | null;
    type: ReminderType | null;
    window: 'due' | 'upcoming' | null;
}

export interface AdminFamilyMemberRow {
    id: number;
    ref: string;
    name: string;
    role: string;
    telegram_user_id: string;
    telegram_chat_id: string;
    timezone: string;
    created_at: string | null;
}

export interface AdminFamilyMemberDetail extends AdminFamilyMemberRow {
    preferences: Record<string, unknown> | null;
    updated_at: string | null;
}

export interface AdminMessageFilters {
    chat_id: string | null;
    processed: 'processed' | 'pending' | null;
}

export interface AdminMessageRow {
    id: number;
    ref: string;
    telegram_chat_id: string;
    summary: string | null;
    original_text: string;
    processed_at: string | null;
    created_at: string | null;
    tasks_count: number;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}
