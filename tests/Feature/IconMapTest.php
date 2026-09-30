<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The app draws its own chrome from two icon maps: one in Blade for markup the server renders,
 * one in dom.js for rows JavaScript builds. Neither has to hold every icon — each carries what
 * its side actually draws — but an icon asked for and not defined fails silently: Blade throws
 * on an undefined key only in debug, and dom.js writes `d="undefined"`, which draws nothing.
 *
 * These tests fail instead.
 */
class IconMapTest extends TestCase
{
    public function test_every_icon_blade_asks_for_is_defined_in_the_blade_map(): void
    {
        $defined = $this->bladeIcons();

        foreach ($this->iconsBladeUses() as $name => $where) {
            $this->assertContains($name, $defined, "{$where} renders the icon \"{$name}\", which icon.blade.php does not define");
        }
    }

    public function test_every_icon_javascript_asks_for_is_defined_in_dom_js(): void
    {
        $defined = $this->scriptIcons();

        foreach ($this->iconsScriptsUse() as $name => $where) {
            $this->assertContains($name, $defined, "{$where} renders the icon \"{$name}\", which the ICONS map in dom.js does not define");
        }
    }

    public function test_neither_map_carries_an_icon_nothing_draws(): void
    {
        // A path kept for an icon nobody renders is one more thing to keep in step for nothing.
        //
        // "Mentioned anywhere" rather than "called literally", because plenty of icons are
        // chosen indirectly: a menu entry carries `icon: 'pencil'` and menu.js passes it on, and
        // showToast picks between two names in a ternary. A name that appears in no file at all
        // is the one that is genuinely dead.
        foreach ($this->bladeIcons() as $name) {
            $this->assertTrue(
                $this->isMentionedIn($this->bladeFiles(), $name),
                "icon.blade.php defines \"{$name}\", which no view mentions"
            );
        }

        // The map's own keys are stripped out, or every icon would trivially mention itself.
        $scripts = $this->scriptsWithoutTheMap();

        foreach ($this->scriptIcons() as $name) {
            // assertTrue rather than assertStringContainsString, or a failure dumps every
            // script into the output instead of naming the icon.
            $this->assertTrue(
                str_contains($scripts, "'{$name}'"),
                "The ICONS map in dom.js defines \"{$name}\", which no script mentions"
            );
        }
    }

    /**
     * @param  array<int, string>  $files
     */
    private function isMentionedIn(array $files, string $name): bool
    {
        foreach ($files as $path) {
            $contents = file_get_contents($path);

            if (str_contains($contents, "'{$name}'") || str_contains($contents, "\"{$name}\"")) {
                return true;
            }
        }

        return false;
    }

    private function scriptsWithoutTheMap(): string
    {
        $scripts = '';

        foreach (glob(resource_path('js/*.js')) as $path) {
            $scripts .= preg_replace('/^const ICONS = \{.*?^\};/ms', '', file_get_contents($path));
        }

        return $scripts;
    }

    /**
     * @return array<int, string>
     */
    private function bladeIcons(): array
    {
        preg_match_all(
            "/^ {8}'([a-z-]+)' => \[/m",
            file_get_contents(resource_path('views/components/icon.blade.php')),
            $matches
        );

        return $matches[1];
    }

    /**
     * @return array<int, string>
     */
    private function scriptIcons(): array
    {
        preg_match('/^const ICONS = \{(.*?)^\};/ms', file_get_contents(resource_path('js/dom.js')), $block);
        $this->assertNotEmpty($block, 'The ICONS map was not found in dom.js');

        preg_match_all("/^ {4}'?([a-z-]+)'?:/m", $block[1], $matches);

        return $matches[1];
    }

    /**
     * Every icon name the Blade views render, whether written inline or held in a view's own
     * array of view definitions.
     *
     * @return array<string, string>
     */
    private function iconsBladeUses(): array
    {
        $used = [];

        foreach ($this->bladeFiles() as $path) {
            $contents = file_get_contents($path);
            $where = basename($path);

            preg_match_all('/<x-icon\s+name="([a-z-]+)"/', $contents, $inline);
            preg_match_all("/'icon' => '([a-z-]+)'/", $contents, $fromArray);

            foreach ([...$inline[1], ...$fromArray[1]] as $name) {
                $used[$name] ??= $where;
            }
        }

        return $used;
    }

    /**
     * @return array<string, string>
     */
    private function iconsScriptsUse(): array
    {
        $used = [];

        foreach (glob(resource_path('js/*.js')) as $path) {
            $contents = file_get_contents($path);

            // Literal calls only, plus the `icon:` a menu entry carries for menu.js to pass on.
            // Deliberately conservative: showToast picks its icon in a ternary, and reading the
            // names out of an expression would take a parser. A call this misses is one fewer
            // check, which is the safe direction to be wrong in.
            preg_match_all("/createIcon\(\s*'([a-z-]+)'/", $contents, $calls);
            preg_match_all("/\bicon: '([a-z-]+)'/", $contents, $entries);

            foreach ([...$calls[1], ...$entries[1]] as $name) {
                $used[$name] ??= basename($path);
            }
        }

        return $used;
    }

    /**
     * @return array<int, string>
     */
    private function bladeFiles(): array
    {
        $files = [];

        $tree = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($tree as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')
                && $file->getFilename() !== 'icon.blade.php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
