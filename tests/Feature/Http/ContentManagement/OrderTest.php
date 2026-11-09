<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContentManagementOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Certification $certification;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用の管理者ユーザーと資格を作成
        $this->admin = User::factory()->admin()->create();
        $this->certification = Certification::factory()->create();
    }

    /** @test */
    public function Part一覧がorderの昇順でソートされて取得できることを検証(): void
    {
        // 意図的に ID の作成順と order の数値を逆にする
        $part2 = Part::factory()->create([
            'certification_id' => $this->certification->id,
            'order' => 2,
            'title' => '2番目のパート',
        ]);

        $part1 = Part::factory()->create([
            'certification_id' => $this->certification->id,
            'order' => 1,
            'title' => '1番目のパート',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.certifications.parts.index', $this->certification));

        $response->assertOk();

        // 取得した Part 一覧の ID の並び順が order の昇順（part1 -> part2）と一致するか検証
        $parts = $response->viewData('parts');
        $this->assertEquals([$part1->id, $part2->id], $parts->pluck('id')->toArray());
    }

    /** @test */
    public function Chapter一覧がorderの昇順でソートされて取得できることを検証(): void
    {
        $part = Part::factory()->create(['certification_id' => $this->certification->id]);

        $chapter2 = Chapter::factory()->create([
            'part_id' => $part->id,
            'order' => 2,
        ]);

        $chapter1 = Chapter::factory()->create([
            'part_id' => $part->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.parts.show', $part));

        $response->assertOk();

        $chapters = $response->viewData('part')->chapters;
        $this->assertEquals([$chapter1->id, $chapter2->id], $chapters->pluck('id')->toArray());
    }

    /** @test */
    public function Section一覧がorderの昇順でソートされて取得できることを検証(): void
    {
        $part = Part::factory()->create(['certification_id' => $this->certification->id]);
        $chapter = Chapter::factory()->create(['part_id' => $part->id]);

        $section2 = Section::factory()->create([
            'chapter_id' => $chapter->id,
            'order' => 2,
        ]);

        $section1 = Section::factory()->create([
            'chapter_id' => $chapter->id,
            'order' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.chapters.show', $chapter));

        $response->assertOk();

        $sections = $response->viewData('chapter')->sections;
        $this->assertEquals([$section1->id, $section2->id], $sections->pluck('id')->toArray());
    }
}
