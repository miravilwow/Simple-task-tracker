<?php

namespace App\Enums;

/**
 * The icons a project may carry, curated from Iconify's Fluent UI set (MIT).
 *
 * The value is the Fluent icon base name, so the id is always
 * "fluent--{value}-24-regular" and the Tailwind class is "icon-[{id}]". Those
 * classes are built at runtime, which Tailwind cannot see, so the same values are
 * repeated in the @source inline(...) list in resources/css/app.css. A case added
 * here without that line renders as an empty box, so CategoryApiTest fails when the
 * two drift apart.
 *
 * The column is a varchar rather than a database enum: 224 values would make an
 * unreadable schema, so the set is closed here through Rule::enum() instead.
 */
enum CategoryIcon: string
{
    case Folder = 'folder';
    case FolderOpen = 'folder-open';
    case Archive = 'archive';
    case Briefcase = 'briefcase';
    case Box = 'box';
    case Building = 'building';
    case BuildingBank = 'building-bank';
    case BuildingHome = 'building-home';
    case BuildingShop = 'building-shop';
    case BuildingFactory = 'building-factory';
    case BuildingRetail = 'building-retail';
    case BuildingGovernment = 'building-government';
    case BuildingMultiple = 'building-multiple';
    case Home = 'home';
    case HomeMore = 'home-more';
    case Desktop = 'desktop';
    case Laptop = 'laptop';
    case Phone = 'phone';
    case Tablet = 'tablet';
    case DeviceMeetingRoom = 'device-meeting-room';
    case Document = 'document';
    case DocumentText = 'document-text';
    case DocumentPdf = 'document-pdf';
    case Notepad = 'notepad';
    case Note = 'note';
    case Book = 'book';
    case BookOpen = 'book-open';
    case Bookmark = 'bookmark';
    case Clipboard = 'clipboard';
    case ClipboardTask = 'clipboard-task';
    case Library = 'library';
    case ReadingList = 'reading-list';
    case TextParagraph = 'text-paragraph';
    case SlideText = 'slide-text';
    case Form = 'form';
    case Table = 'table';
    case Grid = 'grid';
    case Apps = 'apps';
    case Board = 'board';
    case BoardSplit = 'board-split';
    case FolderZip = 'folder-zip';
    case Calendar = 'calendar';
    case CalendarDay = 'calendar-day';
    case CalendarWeekStart = 'calendar-week-start';
    case CalendarMonth = 'calendar-month';
    case Clock = 'clock';
    case ClockAlarm = 'clock-alarm';
    case Timer = 'timer';
    case Hourglass = 'hourglass';
    case History = 'history';
    case Mail = 'mail';
    case MailInbox = 'mail-inbox';
    case Send = 'send';
    case Chat = 'chat';
    case Comment = 'comment';
    case PersonChat = 'person-chat';
    case Call = 'call';
    case Video = 'video';
    case Person = 'person';
    case People = 'people';
    case PersonStar = 'person-star';
    case PersonSupport = 'person-support';
    case PeopleTeam = 'people-team';
    case PeopleCommunity = 'people-community';
    case Handshake = 'handshake';
    case Globe = 'globe';
    case Location = 'location';
    case Map = 'map';
    case Earth = 'earth';
    case Flag = 'flag';
    case CompassNorthwest = 'compass-northwest';
    case Cart = 'cart';
    case ShoppingBag = 'shopping-bag';
    case StoreMicrosoft = 'store-microsoft';
    case Receipt = 'receipt';
    case Money = 'money';
    case MoneyHand = 'money-hand';
    case Wallet = 'wallet';
    case CreditCardPerson = 'credit-card-person';
    case Payment = 'payment';
    case CoinMultiple = 'coin-multiple';
    case Savings = 'savings';
    case Tag = 'tag';
    case TicketDiagonal = 'ticket-diagonal';
    case Gift = 'gift';
    case GiftCard = 'gift-card';
    case Cube = 'cube';
    case Heart = 'heart';
    case HeartPulse = 'heart-pulse';
    case Pill = 'pill';
    case Syringe = 'syringe';
    case Stethoscope = 'stethoscope';
    case Dumbbell = 'dumbbell';
    case Sport = 'sport';
    case SportSoccer = 'sport-soccer';
    case SportBasketball = 'sport-basketball';
    case SportBaseball = 'sport-baseball';
    case SportAmericanFootball = 'sport-american-football';
    case SportHockey = 'sport-hockey';
    case SportCricketBall = 'sport-cricket-ball';
    case Run = 'run';
    case PersonWalking = 'person-walking';
    case Bed = 'bed';
    case WeatherMoon = 'weather-moon';
    case Showerhead = 'showerhead';
    case Food = 'food';
    case FoodApple = 'food-apple';
    case FoodPizza = 'food-pizza';
    case FoodCake = 'food-cake';
    case FoodEgg = 'food-egg';
    case FoodFish = 'food-fish';
    case FoodChickenLeg = 'food-chicken-leg';
    case Drink = 'drink';
    case DrinkCoffee = 'drink-coffee';
    case DrinkBeer = 'drink-beer';
    case DrinkWine = 'drink-wine';
    case BowlChopsticks = 'bowl-chopsticks';
    case Cookies = 'cookies';
    case Airplane = 'airplane';
    case AirplaneTakeOff = 'airplane-take-off';
    case VehicleCar = 'vehicle-car';
    case VehicleBus = 'vehicle-bus';
    case VehicleTruck = 'vehicle-truck';
    case VehicleBicycle = 'vehicle-bicycle';
    case VehicleShip = 'vehicle-ship';
    case VehicleSubway = 'vehicle-subway';
    case VehicleMotorcycle = 'vehicle-motorcycle';
    case Luggage = 'luggage';
    case Beach = 'beach';
    case MountainTrail = 'mountain-trail';
    case Tent = 'tent';
    case City = 'city';
    case AnimalCat = 'animal-cat';
    case AnimalDog = 'animal-dog';
    case AnimalRabbit = 'animal-rabbit';
    case AnimalTurtle = 'animal-turtle';
    case Leaf = 'leaf';
    case TreeDeciduous = 'tree-deciduous';
    case PlantGrass = 'plant-grass';
    case PlantRagweed = 'plant-ragweed';
    case WeatherSunny = 'weather-sunny';
    case WeatherRain = 'weather-rain';
    case WeatherSnowflake = 'weather-snowflake';
    case Water = 'water';
    case Drop = 'drop';
    case MusicNote1 = 'music-note-1';
    case MusicNote2 = 'music-note-2';
    case Headphones = 'headphones';
    case Mic = 'mic';
    case Speaker2 = 'speaker-2';
    case PlayCircle = 'play-circle';
    case VideoClip = 'video-clip';
    case MoviesAndTv = 'movies-and-tv';
    case Camera = 'camera';
    case CameraDome = 'camera-dome';
    case Image = 'image';
    case ImageMultiple = 'image-multiple';
    case PaintBrush = 'paint-brush';
    case PaintBucket = 'paint-bucket';
    case Color = 'color';
    case Pen = 'pen';
    case Highlight = 'highlight';
    case DrawShape = 'draw-shape';
    case DesignIdeas = 'design-ideas';
    case Wand = 'wand';
    case Code = 'code';
    case CodeBlock = 'code-block';
    case Branch = 'branch';
    case Bug = 'bug';
    case Database = 'database';
    case Server = 'server';
    case Cloud = 'cloud';
    case Wifi2 = 'wifi-2';
    case Bluetooth = 'bluetooth';
    case PlugConnected = 'plug-connected';
    case BatteryCharge = 'battery-charge';
    case Lightbulb = 'lightbulb';
    case Flash = 'flash';
    case Rocket = 'rocket';
    case PuzzlePiece = 'puzzle-piece';
    case Toolbox = 'toolbox';
    case Wrench = 'wrench';
    case WrenchScrewdriver = 'wrench-screwdriver';
    case Settings = 'settings';
    case Key = 'key';
    case ShieldTask = 'shield-task';
    case LockClosed = 'lock-closed';
    case Fingerprint = 'fingerprint';
    case HatGraduation = 'hat-graduation';
    case Backpack = 'backpack';
    case Ribbon = 'ribbon';
    case Trophy = 'trophy';
    case StarEmphasis = 'star-emphasis';
    case Premium = 'premium';
    case Certificate = 'certificate';
    case Broom = 'broom';
    case Dust = 'dust';
    case Couch = 'couch';
    case Seat = 'seat';
    case LightbulbFilament = 'lightbulb-filament';
    case BinRecycle = 'bin-recycle';
    case TargetArrow = 'target-arrow';
    case ChartMultiple = 'chart-multiple';
    case DataTrending = 'data-trending';
    case DataPie = 'data-pie';
    case BoardHeart = 'board-heart';
    case Accessibility = 'accessibility';
    case EarthLeaf = 'earth-leaf';
    case Emoji = 'emoji';
    case EmojiLaugh = 'emoji-laugh';
    case EmojiSad = 'emoji-sad';
    case HandWave = 'hand-wave';
    case ThumbLike = 'thumb-like';
    case HeartCircle = 'heart-circle';
    case Sparkle = 'sparkle';
    case Glasses = 'glasses';
    case Scales = 'scales';
    case Balloon = 'balloon';
    case Fireplace = 'fireplace';
    case Games = 'games';
    case XboxConsole = 'xbox-console';
    case PuzzleCube = 'puzzle-cube';
    case WheelchairAccess = 'wheelchair-access';
    case WalkieTalkie = 'walkie-talkie';

    /** Its name in words, used as the accessible label and as the search key. */
    public function label(): string
    {
        return ucfirst(str_replace('-', ' ', $this->value));
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
