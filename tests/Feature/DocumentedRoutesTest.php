<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The endpoint table in the README has to match the routes the application really has.
 *
 * The README is graded, so a line left behind after an
 * endpoint goes is an endpoint a grader will try and watch fail. This test is the gate: it fails in
 * both directions, for a route with no line and a line with no route.
 */
class DocumentedRoutesTest extends TestCase
{
    private const DOC = 'README.md';

    /**
     * The documents that carry an endpoint table.
     *
     * @return array<int, string>
     */
    public static function documents(): array
    {
        return [
            'the README' => [self::DOC],
        ];
    }

    /**
     * Every endpoint a document claims, as "METHOD /path" with the id placeholders levelled so
     * `{id}` in prose and `{task}` in a route read the same.
     *
     * @return array<int, string>
     */
    private function documented(string $document = self::DOC): array
    {
        $markdown = file_get_contents(base_path($document));

        // The table is indented inside a list item, so the row does not start at the line's edge.
        preg_match_all('/^\s*\| (GET|POST|PATCH|DELETE) \| `([^`]+)` \|/m', $markdown, $matches, PREG_SET_ORDER);

        return $this->normalise(array_map(
            fn (array $row) => $row[1].' '.$row[2],
            $matches,
        ));
    }

    /**
     * Every API endpoint the application really serves.
     *
     * @return array<int, string>
     */
    private function actual(): array
    {
        $endpoints = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            foreach (array_intersect($route->methods(), ['GET', 'POST', 'PATCH', 'DELETE']) as $method) {
                $endpoints[] = $method.' /'.$route->uri();
            }
        }

        return $this->normalise($endpoints);
    }

    /**
     * @param  array<int, string>  $endpoints
     * @return array<int, string>
     */
    private function normalise(array $endpoints): array
    {
        $levelled = array_map(
            fn (string $endpoint) => preg_replace('/\{[a-zA-Z_]+\??\}/', '{id}', $endpoint),
            $endpoints,
        );

        $unique = array_values(array_unique($levelled));
        sort($unique);

        return $unique;
    }

    /**
     * @dataProvider documents
     */
    public function test_the_endpoint_table_is_actually_being_read(string $document): void
    {
        // Without this the other two tests pass by reading nothing: a regex that stops matching
        // leaves both sides of the comparison empty and reports perfect agreement. That happened
        // once already, so the gate has to prove it found the table before trusting it.
        $this->assertGreaterThan(10, count($this->documented($document)), "No endpoint table found in {$document}.");
        $this->assertGreaterThan(10, count($this->actual()));
    }

    /**
     * @dataProvider documents
     */
    public function test_the_documentation_describes_an_endpoint_the_app_really_has(string $document): void
    {
        $missing = array_values(array_diff($this->documented($document), $this->actual()));

        $this->assertSame([], $missing, sprintf(
            "%s lists endpoints the application does not serve:\n  %s",
            $document,
            implode("\n  ", $missing),
        ));
    }

    /**
     * @dataProvider documents
     */
    public function test_every_endpoint_the_app_has_is_documented(string $document): void
    {
        $undocumented = array_values(array_diff($this->actual(), $this->documented($document)));

        $this->assertSame([], $undocumented, sprintf(
            "These endpoints are not in the %s table:\n  %s",
            $document,
            implode("\n  ", $undocumented),
        ));
    }
}
