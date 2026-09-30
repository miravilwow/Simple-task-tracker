<?php

namespace App\Enums;

/**
 * The reactions a comment may carry.
 *
 * A closed set rather than free text, for the same reason CategoryIcon is one: the value is
 * rendered straight into the page, and a fixed row of eight is what a reaction is actually for.
 * A searchable picker over 1,900 emoji would need a dataset, which would be the first UI
 * dependency in the project.
 *
 * The values are plain Unicode, so nothing has to be installed to show them.
 */
enum Reaction: string
{
    case ThumbsUp = '👍';
    case Heart = '❤️';
    case Laugh = '😂';
    case Celebrate = '🎉';
    case Eyes = '👀';
    case Rocket = '🚀';
    case Praise = '🙌';
    case Sad = '😢';

    /**
     * The accessible name, because an emoji on its own is read out inconsistently and a
     * reaction button has to say what pressing it means.
     */
    public function label(): string
    {
        return match ($this) {
            self::ThumbsUp => 'Thumbs up',
            self::Heart => 'Heart',
            self::Laugh => 'Laugh',
            self::Celebrate => 'Celebrate',
            self::Eyes => 'Eyes',
            self::Rocket => 'Rocket',
            self::Praise => 'Praise',
            self::Sad => 'Sad',
        };
    }
}
