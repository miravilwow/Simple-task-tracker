<?php

namespace App\Enums;

/**
 * The icons a category may carry, drawn from Iconify's Fluent UI set (MIT).
 *
 * The value is the Fluent icon's base name, so the full id is always
 * "fluent--{value}-24-regular" and the Tailwind class is "icon-[{id}]".
 * Those classes are built at runtime, which Tailwind cannot see, so the list
 * is repeated once in `@source inline(...)` in resources/css/app.css. Adding a
 * case here means adding it there too, or the icon renders as an empty box.
 */
enum CategoryIcon: string
{
    case Folder = 'folder';
    case Briefcase = 'briefcase';
    case Person = 'person';
    case Graduation = 'hat-graduation';
    case Book = 'book';
    case Home = 'home';
    case Cart = 'cart';
    case Heart = 'heart';
    case Dumbbell = 'dumbbell';
    case Money = 'money';
    case Airplane = 'airplane';
    case Food = 'food';
    case Games = 'games';
    case People = 'people';
    case Lightbulb = 'lightbulb';
    case Calendar = 'calendar';
    case Code = 'code';
    case PaintBrush = 'paint-brush';
    case Car = 'vehicle-car';
    case PawPrint = 'animal-paw-print';
    case MusicNote = 'music-note-2';
    case Pill = 'pill';
    case Gift = 'gift';
    case Leaf = 'leaf';

    /** What this icon is for, used as its accessible name in the picker. */
    public function label(): string
    {
        return match ($this) {
            self::Folder => 'General',
            self::Briefcase => 'Work',
            self::Person => 'Personal',
            self::Graduation => 'School',
            self::Book => 'Study',
            self::Home => 'Home',
            self::Cart => 'Shopping',
            self::Heart => 'Health',
            self::Dumbbell => 'Fitness',
            self::Money => 'Finance',
            self::Airplane => 'Travel',
            self::Food => 'Food',
            self::Games => 'Gaming',
            self::People => 'Family',
            self::Lightbulb => 'Ideas',
            self::Calendar => 'Events',
            self::Code => 'Coding',
            self::PaintBrush => 'Creative',
            self::Car => 'Errands',
            self::PawPrint => 'Pets',
            self::MusicNote => 'Music',
            self::Pill => 'Medication',
            self::Gift => 'Occasions',
            self::Leaf => 'Garden',
        };
    }

    public function cssClass(): string
    {
        return "icon-[fluent--{$this->value}-24-regular]";
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
