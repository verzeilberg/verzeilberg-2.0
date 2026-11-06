<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\AnswerGiven;
use App\Tests\Checklist\ChecklistTestTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/** app:checklist:resync-answers rebuilds the AnswerGiven records from the item content */
class ChecklistResyncAnswersCommandTest extends WebTestCase
{
    use ChecklistTestTrait;

    protected function setUp(): void
    {
        $this->setUpChecklistTest();
    }

    protected function tearDown(): void
    {
        $this->tearDownChecklistTest();
        parent::tearDown();
    }

    public function testRebuildsMissingAndStaleAnswers(): void
    {
        $checkList = $this->createCheckList('Resync');
        $this->createField($checkList, 'Naam', 'text');
        $this->createField($checkList, 'Fruit', 'checkbox', answers: ['a' => 'Appel', 'p' => 'Peer']);
        // Created directly, so it has no AnswerGiven records yet
        $item = $this->createItem($checkList, ['field_naam' => 'Jan', 'field_fruit' => ['a', 'p']]);
        $this->assertSame(0, $this->answerCount($item->getId()));

        $tester = new CommandTester((new Application(self::$kernel))->find('app:checklist:resync-answers'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertMatchesRegularExpression('/Rebuilt the answers of \d+ checklist item\(s\)\./', $tester->getDisplay());
        $this->assertSame(3, $this->answerCount($item->getId()), 'one for the text, one per chosen fruit');

        // Running it again doesn't add duplicates, and follows a changed content
        $item->setItemContent(['field_naam' => 'Jan']);
        $this->em->flush();
        $tester->execute([]);
        $this->assertSame(1, $this->answerCount($item->getId()));
    }

    private function answerCount(?int $itemId): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM answers_given WHERE checklist_item_id = ?', [$itemId]);
    }
}
