<?php

namespace App\Services;

use InvalidArgumentException;

/** Deterministic, gender-independent character portraits with no external assets. */
class RpgProfileCatalog
{
    public const CAPACITY = 200;

    private const MAX_PROFILE_ID = 4294967295;

    private const CLASSES = [
        ['name' => 'Knight', 'given' => 'Arin'],
        ['name' => 'Mage', 'given' => 'Lumi'],
        ['name' => 'Ranger', 'given' => 'Kori'],
        ['name' => 'Rogue', 'given' => 'Vey'],
        ['name' => 'Paladin', 'given' => 'Tavi'],
        ['name' => 'Druid', 'given' => 'Rowan'],
        ['name' => 'Bard', 'given' => 'Niko'],
        ['name' => 'Monk', 'given' => 'Sora'],
        ['name' => 'Alchemist', 'given' => 'Pax'],
        ['name' => 'Berserker', 'given' => 'Riven'],
        ['name' => 'Necromancer', 'given' => 'Nyx'],
        ['name' => 'Samurai', 'given' => 'Ren'],
        ['name' => 'Sentinel', 'given' => 'Cato'],
        ['name' => 'Artificer', 'given' => 'Mika'],
        ['name' => 'Oracle', 'given' => 'Sage'],
        ['name' => 'Warden', 'given' => 'Oryn'],
        ['name' => 'Duelist', 'given' => 'Vale'],
        ['name' => 'Runekeeper', 'given' => 'Ivo'],
        ['name' => 'Beastkeeper', 'given' => 'Wren'],
        ['name' => 'Starcaller', 'given' => 'Nova'],
    ];

    private const ELEMENTS = [
        ['name' => 'Ember', 'surname' => 'Emberfall', 'base' => '#F17945', 'shade' => '#8D3B34', 'light' => '#FFD28C', 'background' => '#2E1D2C'],
        ['name' => 'Tide', 'surname' => 'Tidesong', 'base' => '#4DABDB', 'shade' => '#285184', 'light' => '#B2ECF2', 'background' => '#152D42'],
        ['name' => 'Grove', 'surname' => 'Mosswake', 'base' => '#8BC66D', 'shade' => '#3A684E', 'light' => '#E0F1A9', 'background' => '#1C332E'],
        ['name' => 'Storm', 'surname' => 'Stormvale', 'base' => '#AC8BE9', 'shade' => '#594189', 'light' => '#EFDCFF', 'background' => '#28213F'],
        ['name' => 'Frost', 'surname' => 'Frostveil', 'base' => '#76D7D9', 'shade' => '#37778D', 'light' => '#D7FFFA', 'background' => '#17323C'],
        ['name' => 'Dawn', 'surname' => 'Dawncrest', 'base' => '#E8BE58', 'shade' => '#8C633D', 'light' => '#FFF0B2', 'background' => '#302C28'],
        ['name' => 'Dusk', 'surname' => 'Duskwisp', 'base' => '#C780BE', 'shade' => '#72456E', 'light' => '#FFD6F3', 'background' => '#30213C'],
        ['name' => 'Stone', 'surname' => 'Stoneheart', 'base' => '#B9AC8B', 'shade' => '#666454', 'light' => '#ECE5CD', 'background' => '#292F30'],
        ['name' => 'Bloom', 'surname' => 'Bloomwhisper', 'base' => '#EC98B1', 'shade' => '#965C7D', 'light' => '#FFE3DD', 'background' => '#352436'],
        ['name' => 'Astral', 'surname' => 'Starwake', 'base' => '#829BEF', 'shade' => '#495594', 'light' => '#D8E1FF', 'background' => '#1D2645'],
    ];

    private const SKIN = ['#D7A880', '#AE795A', '#E7BE9A', '#89573F', '#BF8A65', '#72503F'];

    /** @return array{id:int,name:string,class:string,element:string,level:int,avatar_url:string,sprite_url:string} */
    public function profile(int $id): array
    {
        $profile = $this->identity($id);
        $profile['avatar_url'] = route('rpg.avatar', ['profile' => $id]);
        $profile['sprite_url'] = route('rpg.sprite', ['profile' => $id]);

        return $profile;
    }

    public function svg(int $id): string
    {
        $profile = $this->identity($id);
        $slot = ($id - 1) % self::CAPACITY;
        $class = intdiv($slot, 10);
        $element = $slot % 10;
        $palette = self::ELEMENTS[$element];
        $base = $palette['base'];
        $shade = $palette['shade'];
        $light = $palette['light'];
        $background = $palette['background'];
        $title = htmlspecialchars($profile['name'].' · '.$profile['element'].' '.$profile['class'], ENT_QUOTES | ENT_XML1, 'UTF-8');
        $character = $this->character($class, $base, $shade, $light);
        $effect = $this->elementEffect($element, $base, $light);
        $crest = $this->crest($id, $base, $light);
        $number = '#'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
        $numberSize = $id > 999999 ? '5' : '7';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128" role="img" aria-labelledby="avatar-title" shape-rendering="crispEdges">
  <title id="avatar-title">{$title}</title>
  <desc>An original fantasy adventurer portrait with a unique numbered guild crest.</desc>
  <rect width="128" height="128" rx="18" fill="#101726"/>
  <path d="M16 5H112V10H122V112H117V122H11V117H5V16H10V10H16Z" fill="{$background}"/>
  <path d="M11 12H57L11 67Z" fill="{$base}" opacity=".10"/>
  <path d="M118 64V115H65Z" fill="{$base}" opacity=".08"/>
  <path d="M13 28V13H28M100 13H115V28M13 100V115H28M100 115H115V100" fill="none" stroke="{$base}" stroke-width="2" opacity=".55"/>
  {$effect}
  <path d="M38 111H90V116H38Z" fill="#0B1120" opacity=".8"/>
  {$character}
  {$crest}
  <text x="13" y="118" fill="{$light}" font-family="monospace" font-size="{$numberSize}" font-weight="600">{$number}</text>
</svg>
SVG;
    }

    /** The same character art without the portrait tile, effects, crest or labels. */
    public function spriteSvg(int $id): string
    {
        $this->identity($id);
        $slot = ($id - 1) % self::CAPACITY;
        $palette = self::ELEMENTS[$slot % 10];
        $character = $this->character(intdiv($slot, 10), $palette['base'], $palette['shade'], $palette['light']);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="112" height="106" viewBox="12 10 112 106" role="img" aria-labelledby="sprite-title" shape-rendering="crispEdges">
  <title id="sprite-title">Player avatar</title>
  {$character}
</svg>
SVG;
    }

    private function character(int $class, string $base, string $shade, string $light): string
    {
        $skin = self::SKIN[$class % count(self::SKIN)];
        $outfit = $this->outfit($class, $base, $shade, $light);

        return <<<SVG
<g stroke="#101726" stroke-width="3" stroke-linejoin="miter">
    <path d="M46 96H60V111H43V105H46ZM69 96H82V105H85V111H69Z" fill="#343646"/>
    <path d="M43 76L36 81L33 99L43 102L50 84ZM84 76L92 82L96 99L86 102L78 84Z" fill="{$shade}"/>
    <path d="M45 74H83L90 102H38Z" fill="{$base}"/>
    <path d="M57 75H71V102H57Z" fill="{$shade}" stroke="none"/>
    <path d="M43 94H85V101H43Z" fill="#414052"/>
    <rect x="59" y="94" width="11" height="8" fill="{$light}"/>
    <path d="M36 94H44V103H34V98H36ZM85 94H93V98H96V103H85Z" fill="{$skin}"/>
    {$outfit['body']}
    <path d="M43 52H49V64H42V57H43ZM79 52H85V57H87V64H79Z" fill="{$skin}"/>
    <path d="M46 39H82V68L76 75H52L46 68Z" fill="{$skin}"/>
    <path d="M46 39H82V49H75V45H66V49H54V53H46Z" fill="#34303B" stroke="none"/>
    <path d="M53 57H59V63H53ZM70 57H76V63H70Z" fill="#202331" stroke="none"/>
    <path d="M54 57H56V59H54ZM71 57H73V59H71Z" fill="#FFF4DF" stroke="none"/>
    <path d="M63 64H66V68H63Z" fill="#3D2730" opacity=".25" stroke="none"/>
    <path d="M59 70H69V72H59Z" fill="#3D2730" opacity=".55" stroke="none"/>
    {$outfit['head']}
    {$outfit['gear']}
</g>
SVG;
    }

    /** @return array{id:int,name:string,class:string,element:string,level:int} */
    private function identity(int $id): array
    {
        if ($id < 1 || $id > self::MAX_PROFILE_ID) {
            throw new InvalidArgumentException('RPG profile IDs must be between 1 and 4294967295.');
        }

        $slot = ($id - 1) % self::CAPACITY;
        $class = self::CLASSES[intdiv($slot, 10)];
        $element = self::ELEMENTS[$slot % 10];
        $name = $class['given'].' '.$element['surname'];

        if ($id > self::CAPACITY) {
            $name .= ' #'.$id;
        }

        return ['id' => $id, 'name' => $name, 'class' => $class['name'], 'element' => $element['name'], 'level' => 1];
    }

    /** @return array{head:string,body:string,gear:string} */
    private function outfit(int $class, string $base, string $shade, string $light): array
    {
        $metal = '#C5D1DE';
        $steel = '#75859A';
        $wood = '#9C765B';

        return match ($class) {
            0 => [ // Knight: plumed helmet, breastplate and broad sword.
                'head' => "<path d=\"M59 32V20H71V27H66V37Z\" fill=\"{$base}\"/><path d=\"M42 54V38L50 31H78L86 38V54H78V46H50V54Z\" fill=\"{$metal}\"/><path d=\"M42 53H49V68H42ZM79 53H86V68H79Z\" fill=\"{$steel}\"/>",
                'body' => "<path d=\"M48 77H80L76 94H52Z\" fill=\"{$metal}\"/><path d=\"M60 79H68V89H60Z\" fill=\"{$base}\" stroke=\"none\"/>",
                'gear' => "<path d=\"M99 45L105 53V90H95V53Z\" fill=\"{$metal}\"/><path d=\"M90 88H110V94H90Z\" fill=\"{$light}\"/><path d=\"M97 94H103V108H97Z\" fill=\"{$wood}\"/>",
            ],
            1 => [ // Mage: tall bent hat and crystal staff.
                'head' => "<path d=\"M39 44L58 16H77L67 24L82 44Z\" fill=\"{$shade}\"/><path d=\"M32 43H93V50H32Z\" fill=\"{$base}\"/><path d=\"M59 31H67V39H59Z\" fill=\"{$light}\"/>",
                'body' => "<path d=\"M48 76L58 87L64 78L70 87L81 76\" fill=\"none\" stroke=\"{$light}\"/>",
                'gear' => "<path d=\"M97 51H103V108H97Z\" fill=\"{$wood}\"/><path d=\"M100 31L110 43L100 55L90 43Z\" fill=\"{$light}\"/><path d=\"M100 35V49\" stroke=\"{$base}\"/>",
            ],
            2 => [ // Ranger: angular hood, shoulder cape and drawn bow.
                'head' => "<path d=\"M40 65V39L64 26L88 39V65H79V46H49V65Z\" fill=\"{$shade}\"/><path d=\"M49 41L64 33L79 41\" fill=\"none\" stroke=\"{$base}\"/>",
                'body' => "<path d=\"M42 74L64 84L86 74L80 91L64 85L48 91Z\" fill=\"{$shade}\"/>",
                'gear' => "<path d=\"M97 43L107 55L111 72L107 90L97 101\" fill=\"none\" stroke=\"{$wood}\" stroke-width=\"5\"/><path d=\"M97 43V101M86 72H112\" fill=\"none\" stroke=\"{$light}\" stroke-width=\"2\"/><path d=\"M111 68L117 72L111 76Z\" fill=\"{$metal}\" stroke=\"none\"/>",
            ],
            3 => [ // Rogue: masked hood, scarf tails and two short daggers.
                'head' => "<path d=\"M41 58V39L62 28L87 40V60H79V45H49V58Z\" fill=\"{$shade}\"/><path d=\"M46 63H82V73L64 80L46 73Z\" fill=\"{$shade}\"/><path d=\"M44 70L28 68L22 75L45 76\" fill=\"{$base}\"/>",
                'body' => "<path d=\"M47 79L78 95M79 79L49 95\" stroke=\"{$light}\" stroke-width=\"4\"/>",
                'gear' => "<path d=\"M23 66L33 76L39 94L33 97L25 80ZM106 66L97 76L91 94L97 97L104 80Z\" fill=\"{$metal}\"/><path d=\"M28 95L40 91M90 91L102 95\" stroke=\"{$base}\" stroke-width=\"5\"/>",
            ],
            4 => [ // Paladin: radiant circlet and cross-marked kite shield.
                'head' => "<path d=\"M44 46L42 33L54 39L64 27L74 39L86 33L84 46Z\" fill=\"{$light}\"/><path d=\"M59 39H69V49H59Z\" fill=\"{$base}\"/>",
                'body' => "<path d=\"M47 77H81V92H47Z\" fill=\"{$metal}\"/><path d=\"M61 78H67V92H61ZM55 83H73V88H55Z\" fill=\"{$light}\" stroke=\"none\"/>",
                'gear' => "<path d=\"M18 73L33 68L48 73V94L33 109L18 94Z\" fill=\"{$base}\"/><path d=\"M30 77H36V99H30ZM24 84H42V90H24Z\" fill=\"{$light}\" stroke=\"none\"/>",
            ],
            5 => [ // Druid: branching antlers, leaf mantle and living staff.
                'head' => "<path d=\"M49 43L38 33V22M38 32L29 28V20M79 43L90 33V22M90 32L99 28V20\" fill=\"none\" stroke=\"{$wood}\" stroke-width=\"5\"/><path d=\"M44 42L55 35L64 42L74 35L84 42L77 50H51Z\" fill=\"{$base}\"/>",
                'body' => "<path d=\"M42 75L54 74L64 84L74 74H86L77 92L64 85L51 92Z\" fill=\"{$shade}\"/><path d=\"M49 79L55 85M79 79L73 85\" stroke=\"{$light}\" stroke-width=\"2\"/>",
                'gear' => "<path d=\"M99 106V55L106 46\" fill=\"none\" stroke=\"{$wood}\" stroke-width=\"6\"/><path d=\"M103 49L93 44V34L105 38L110 49Z\" fill=\"{$base}\"/><path d=\"M108 47L116 41V33L105 37Z\" fill=\"{$light}\"/>",
            ],
            6 => [ // Bard: feathered cap and a pixel lute.
                'head' => "<path d=\"M40 44L47 31L79 35L85 44Z\" fill=\"{$shade}\"/><path d=\"M34 43H91V50H34Z\" fill=\"{$base}\"/><path d=\"M74 37L82 20L95 17L91 28Z\" fill=\"{$light}\"/>",
                'body' => "<path d=\"M49 76L64 88L79 76\" fill=\"none\" stroke=\"{$light}\" stroke-width=\"5\"/>",
                'gear' => "<path d=\"M89 66H97V88H89Z\" fill=\"{$wood}\"/><path d=\"M84 83H101L108 91V101L101 107H84L77 101V91Z\" fill=\"#C79B69\"/><rect x=\"88\" y=\"91\" width=\"10\" height=\"8\" fill=\"{$shade}\"/><path d=\"M91 70V102M95 70V102\" stroke=\"{$light}\" stroke-width=\"1\"/>",
            ],
            7 => [ // Monk: tied headband, bead collar and wrapped quarterstaff.
                'head' => "<path d=\"M45 44H83V52H45Z\" fill=\"{$base}\"/><path d=\"M44 47L31 45L27 54H43L34 64L45 58\" fill=\"{$shade}\"/><rect x=\"60\" y=\"44\" width=\"9\" height=\"8\" fill=\"{$light}\"/>",
                'body' => "<path d=\"M49 76H55V82H49ZM57 81H63V87H57ZM65 81H71V87H65ZM73 76H79V82H73Z\" fill=\"{$wood}\" stroke-width=\"2\"/>",
                'gear' => "<path d=\"M23 90H105V97H23Z\" fill=\"{$wood}\"/><path d=\"M29 90V97M35 90V97M92 90V97M98 90V97\" stroke=\"{$light}\" stroke-width=\"3\"/>",
            ],
            8 => [ // Alchemist: broad goggles and a luminous flask.
                'head' => "<path d=\"M43 43H85V51H43Z\" fill=\"{$wood}\"/><rect x=\"47\" y=\"43\" width=\"15\" height=\"13\" fill=\"{$light}\"/><rect x=\"67\" y=\"43\" width=\"15\" height=\"13\" fill=\"{$light}\"/><path d=\"M62 48H67\" stroke=\"{$steel}\"/>",
                'body' => "<path d=\"M49 77L59 84L64 79L69 84L79 77L76 98H52Z\" fill=\"{$light}\"/><path d=\"M62 87H68V92H62Z\" fill=\"{$base}\" stroke=\"none\"/>",
                'gear' => "<path d=\"M94 70H104V83L113 96V105H85V96L94 83Z\" fill=\"{$light}\"/><path d=\"M91 94H107L110 99V103H88V99Z\" fill=\"{$base}\" stroke=\"none\"/><path d=\"M92 68H106V74H92Z\" fill=\"{$wood}\"/>",
            ],
            9 => [ // Berserker: swept horns and a heavy battle axe.
                'head' => "<path d=\"M46 41L31 35L27 21L35 26L40 31L50 33ZM82 41L97 35L101 21L93 26L88 31L78 33Z\" fill=\"{$light}\"/><path d=\"M43 48V36L51 29H77L85 36V48Z\" fill=\"{$steel}\"/><path d=\"M60 31H68V49H60Z\" fill=\"{$base}\"/>",
                'body' => "<path d=\"M44 76L82 96M83 76L47 96\" stroke=\"{$wood}\" stroke-width=\"7\"/><rect x=\"59\" y=\"82\" width=\"10\" height=\"10\" fill=\"{$metal}\"/>",
                'gear' => "<path d=\"M96 49H102V108H96Z\" fill=\"{$wood}\"/><path d=\"M94 48L108 42L117 48V68L108 74L94 66Z\" fill=\"{$metal}\"/><path d=\"M109 47V69\" stroke=\"{$base}\" stroke-width=\"3\"/>",
            ],
            10 => [ // Necromancer: bone-framed hood and skull staff.
                'head' => "<path d=\"M40 68V36L64 24L88 36V68H79V44H49V68Z\" fill=\"{$shade}\"/><path d=\"M46 39H56V45H49V55H43V44ZM72 39H82L85 44V55H79V45H72Z\" fill=\"#E6DFC9\"/>",
                'body' => "<path d=\"M51 77L64 90L77 77\" stroke=\"#E6DFC9\" stroke-width=\"5\" fill=\"none\"/><rect x=\"60\" y=\"87\" width=\"8\" height=\"8\" fill=\"{$light}\"/>",
                'gear' => "<path d=\"M97 57H103V108H97Z\" fill=\"{$wood}\"/><path d=\"M91 35H109L113 40V54H107V60H93V54H87V40Z\" fill=\"#E6DFC9\"/><path d=\"M92 43H98V49H92ZM103 43H109V49H103Z\" fill=\"{$shade}\" stroke=\"none\"/>",
            ],
            11 => [ // Samurai: crested kabuto and katana.
                'head' => "<path d=\"M39 51L44 36L53 29H75L84 36L89 51H80V44H48V51Z\" fill=\"{$shade}\"/><path d=\"M48 31L43 19L55 26L64 35L73 26L85 19L80 31L64 43Z\" fill=\"{$light}\"/><path d=\"M40 51H48V68H38ZM80 51H88L90 68H80Z\" fill=\"{$base}\"/>",
                'body' => "<path d=\"M47 78H81V84H47ZM46 87H82V92H46Z\" fill=\"{$shade}\" stroke-width=\"2\"/>",
                'gear' => "<path d=\"M103 47L109 52L92 95L86 92Z\" fill=\"{$metal}\"/><path d=\"M82 89L97 95\" stroke=\"{$light}\" stroke-width=\"5\"/><path d=\"M88 95L83 108\" stroke=\"{$wood}\" stroke-width=\"6\"/>",
            ],
            12 => [ // Sentinel: enclosed visor and tall tower shield.
                'head' => "<path d=\"M43 39L51 31H77L85 39V70L77 76H51L43 70Z\" fill=\"{$steel}\"/><path d=\"M48 51H80V59H48Z\" fill=\"#202331\"/><path d=\"M53 54H59V57H53ZM69 54H75V57H69Z\" fill=\"{$light}\" stroke=\"none\"/><path d=\"M60 62H68V75H60Z\" fill=\"{$metal}\" stroke=\"none\"/>",
                'body' => "<path d=\"M43 76H85V90L76 96H52L43 90Z\" fill=\"{$steel}\"/><path d=\"M58 79H70V91H58Z\" fill=\"{$base}\"/>",
                'gear' => "<path d=\"M16 64H43L49 71V106H16Z\" fill=\"{$base}\"/><path d=\"M22 71H38V99H22Z\" fill=\"{$shade}\"/><path d=\"M30 76L35 85L30 94L25 85Z\" fill=\"{$light}\" stroke=\"none\"/>",
            ],
            13 => [ // Artificer: riveted cap, monocle and oversized wrench.
                'head' => "<path d=\"M44 43V33H82V43H88V49H40V43Z\" fill=\"{$wood}\"/><path d=\"M50 36H55V40H50ZM71 36H76V40H71Z\" fill=\"{$light}\" stroke=\"none\"/><rect x=\"68\" y=\"53\" width=\"14\" height=\"13\" fill=\"{$light}\"/><path d=\"M81 64L84 78\" stroke=\"{$wood}\" stroke-width=\"2\"/>",
                'body' => "<path d=\"M50 76H57V86H71V76H78V100H50Z\" fill=\"{$shade}\"/><rect x=\"59\" y=\"88\" width=\"10\" height=\"7\" fill=\"{$light}\"/>",
                'gear' => "<path d=\"M93 47V36H99V46H105V36H111V47L105 55V106H97V55Z\" fill=\"{$metal}\"/><path d=\"M99 74H103V96H99Z\" fill=\"{$base}\" stroke=\"none\"/>",
            ],
            14 => [ // Oracle: luminous veil, third-eye jewel and divination orb.
                'head' => "<path d=\"M39 71V40L49 27H79L89 40V71H81V44H47V71Z\" fill=\"{$light}\"/><path d=\"M47 40H81V47H47Z\" fill=\"{$base}\"/><path d=\"M64 33L70 40L64 47L58 40Z\" fill=\"{$shade}\"/>",
                'body' => "<path d=\"M45 76L55 98H73L83 76L64 86Z\" fill=\"{$light}\"/><path d=\"M61 84H67V93H61Z\" fill=\"{$base}\" stroke=\"none\"/>",
                'gear' => "<path d=\"M94 73H108L115 81V95L108 102H94L87 95V81Z\" fill=\"{$light}\"/><path d=\"M101 79L108 87L101 95L94 87Z\" fill=\"{$base}\" stroke=\"none\"/><path d=\"M89 103H113V108H89Z\" fill=\"{$shade}\"/>",
            ],
            15 => [ // Warden: leaf helm, layered pauldron and long spear.
                'head' => "<path d=\"M41 49L43 36L56 29L64 20L72 29L85 36L87 49H78L64 40L50 49Z\" fill=\"{$shade}\"/><path d=\"M64 25V43M49 37L60 42M79 37L68 42\" stroke=\"{$light}\" stroke-width=\"2\"/>",
                'body' => "<path d=\"M34 79L45 70L58 78L53 91L40 88Z\" fill=\"{$base}\"/><path d=\"M42 78L49 82L44 85\" fill=\"none\" stroke=\"{$light}\"/>",
                'gear' => "<path d=\"M98 49H104V109H98Z\" fill=\"{$wood}\"/><path d=\"M101 25L110 42L101 54L92 42Z\" fill=\"{$metal}\"/><path d=\"M101 31V47\" stroke=\"{$base}\" stroke-width=\"2\"/>",
            ],
            16 => [ // Duelist: tricorn, ruffled collar and a narrow rapier.
                'head' => "<path d=\"M30 31L48 37L64 25L80 37L98 31L87 48H41Z\" fill=\"{$shade}\"/><path d=\"M36 36L45 43H83L92 36\" fill=\"none\" stroke=\"{$light}\" stroke-width=\"2\"/>",
                'body' => "<path d=\"M48 75L55 83L64 76L73 83L80 75L74 91L64 86L54 91Z\" fill=\"{$light}\"/>",
                'gear' => "<path d=\"M109 47L91 94\" stroke=\"{$metal}\" stroke-width=\"4\"/><path d=\"M87 88H98L102 94L97 100H86L82 94Z\" fill=\"none\" stroke=\"{$light}\" stroke-width=\"3\"/><path d=\"M89 98L85 109\" stroke=\"{$wood}\" stroke-width=\"5\"/>",
            ],
            17 => [ // Runekeeper: squared rune hood and floating open spellbook.
                'head' => "<path d=\"M39 69V31H88V69H79V45H49V69Z\" fill=\"{$shade}\"/><path d=\"M48 35H55V40H48ZM61 35H68V40H61ZM74 35H81V40H74Z\" fill=\"{$light}\" stroke=\"none\"/>",
                'body' => "<path d=\"M59 78H69V85L64 92L59 85Z\" fill=\"{$light}\"/>",
                'gear' => "<path d=\"M78 80L95 83L112 78V102L95 107L78 103Z\" fill=\"{$light}\"/><path d=\"M95 84V105M83 86L90 88M83 93L90 95M100 87L107 85M100 94L107 92\" stroke=\"{$shade}\" stroke-width=\"2\"/>",
            ],
            18 => [ // Beastkeeper: animal-eared hood and small wolf companion.
                'head' => "<path d=\"M41 51V25L55 34H74L87 25V51H79V44H49V51Z\" fill=\"{$shade}\"/><path d=\"M47 33V41H55ZM81 33V41H73Z\" fill=\"{$light}\" stroke=\"none\"/>",
                'body' => "<path d=\"M45 75L52 82L58 77L64 84L70 77L76 82L84 75L79 90H49Z\" fill=\"{$light}\"/>",
                'gear' => "<path d=\"M17 89V72L28 79H36L46 72V91L39 104H24Z\" fill=\"{$steel}\"/><path d=\"M23 87H28V92H23ZM35 87H40V92H35Z\" fill=\"{$light}\" stroke=\"none\"/><path d=\"M29 95H35L32 100Z\" fill=\"#202331\" stroke=\"none\"/>",
            ],
            19 => [ // Starcaller: celestial crown and an eight-point star sceptre.
                'head' => "<path d=\"M42 44L39 28L52 36L64 19L76 36L89 28L86 44Z\" fill=\"{$base}\"/><path d=\"M64 29L68 36L64 43L60 36Z\" fill=\"{$light}\"/>",
                'body' => "<path d=\"M47 76H81L76 93L64 87L52 93Z\" fill=\"{$shade}\"/><path d=\"M64 78L67 83L73 84L68 88L69 94L64 91L59 94L60 88L55 84L61 83Z\" fill=\"{$light}\" stroke=\"none\"/>",
                'gear' => "<path d=\"M98 57H104V108H98Z\" fill=\"{$steel}\"/><path d=\"M101 29L105 39L115 36L111 46L119 51L108 55L109 65L101 59L93 65L94 55L83 51L91 46L87 36L97 39Z\" fill=\"{$light}\"/><rect x=\"97\" y=\"44\" width=\"8\" height=\"9\" fill=\"{$base}\" stroke=\"none\"/>",
            ],
        };
    }

    private function elementEffect(int $element, string $base, string $light): string
    {
        $shape = match ($element) {
            0 => '<path d="M17 54V43L23 49V35L30 43V56L24 63Z M103 23V15L109 21V30L104 34L99 29V22Z"/>',
            1 => '<path d="M13 56H19V50H25V54H32V60H26V57H20V63H13ZM98 18H105V14H112V19H118V25H111V22H105V26H98Z"/>',
            2 => '<path d="M15 43H26L31 49V57H22L15 50ZM98 19H111V28L105 34H97V26Z"/>',
            3 => '<path d="M22 34H32L26 46H34L17 66L22 51H15ZM104 13H114L109 24H116L101 38L105 26H99Z"/>',
            4 => '<path d="M21 37H25V62H21ZM11 47H35V51H11ZM99 20H119V24H99ZM107 12H111V32H107ZM14 40L17 37L32 58L29 61Z"/>',
            5 => '<path d="M17 43H29V55H17ZM21 32H25V39H21ZM21 59H25V66H21ZM7 47H14V51H7ZM32 47H39V51H32ZM101 17H113V29H101Z"/>',
            6 => '<path d="M21 34H30V40H23V52H30V58H21L14 51V41ZM105 15H113V21H107V30H113V36H105L99 29V22Z"/>',
            7 => '<path d="M16 38L28 34L34 48L26 58L15 53ZM101 16L114 20L116 32L104 36L98 26Z"/>',
            8 => '<path d="M20 36H27V43H34V50H27V57H20V50H13V43H20ZM104 13H111V20H118V27H111V34H104V27H97V20H104Z"/>',
            9 => '<path d="M24 34L28 44L37 48L28 52L24 62L20 52L11 48L20 44ZM107 12L110 20L118 23L110 27L107 35L104 27L96 23L104 20Z"/>',
        };

        return '<g fill="'.$base.'" opacity=".42">'.$shape.'</g>'
            .'<path d="M17 79H21V83H17ZM109 65H113V69H109ZM35 18H38V21H35Z" fill="'.$light.'" opacity=".7"/>';
    }

    /** A fixed-size 32-bit sigil plus the visible number keeps legacy IDs distinct. */
    private function crest(int $id, string $base, string $light): string
    {
        $svg = '<path d="M91 101H116V116H91Z" fill="#101726" stroke="'.$base.'" stroke-width="2"/>';

        for ($bit = 0; $bit < 32; $bit++) {
            $x = 95 + ($bit % 8) * 2;
            $y = 105 + intdiv($bit, 8) * 2;
            $color = (($id >> $bit) & 1) === 1 ? $light : '#354151';
            $svg .= '<rect x="'.$x.'" y="'.$y.'" width="2" height="2" fill="'.$color.'"/>';
        }

        return $svg;
    }
}
