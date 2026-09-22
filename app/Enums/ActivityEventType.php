<?php

namespace App\Enums;

enum ActivityEventType: string
{
    case CommentReply = 'comment_reply';
    case PrivateReply = 'private_reply';
    case MessageReply = 'message_reply';
    case FlowStep = 'flow_step';

    public static function automatedReplies(): array
    {
        return [
            self::CommentReply,
            self::PrivateReply,
            self::MessageReply,
            self::FlowStep,
        ];
    }
}
