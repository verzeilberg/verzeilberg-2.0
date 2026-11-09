<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Contact;
use App\Tests\Support\MathCaptchaSolver;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The contact form asks a small sum and has a hidden honeypot field, so bots can't send messages.
 */
class ContactSpamTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->client->setServerParameter('REMOTE_ADDR', '10.3.' . random_int(0, 255) . '.' . random_int(1, 254));
        $this->connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testMessageWithTheRightAnswerIsSent(): void
    {
        $before = $this->contactCount();

        $crawler = $this->client->request('GET', '/contact');
        $this->submit($crawler, ['contact[captcha]' => MathCaptchaSolver::answer($crawler)]);

        $this->assertResponseRedirects('/contact');
        $this->assertSame($before + 1, $this->contactCount());
    }

    public function testWrongAnswerIsRefusedWithANewSum(): void
    {
        $before = $this->contactCount();

        $crawler = $this->client->request('GET', '/contact');
        $wrong = (string) ((int) MathCaptchaSolver::answer($crawler) + 1);
        $crawler = $this->submit($crawler, ['contact[captcha]' => $wrong]);

        $this->assertResponseIsUnprocessable();
        $this->assertSelectorTextContains('#contact_form', 'Dat antwoord klopt niet');
        $this->assertSame($before, $this->contactCount());

        // The new sum on the page is the one that counts now
        $this->submit($crawler, ['contact[captcha]' => MathCaptchaSolver::answer($crawler)]);
        $this->assertResponseRedirects('/contact');
        $this->assertSame($before + 1, $this->contactCount());
    }

    public function testASolvedSumCanNotBeReplayed(): void
    {
        $crawler = $this->client->request('GET', '/contact');
        $answer = MathCaptchaSolver::answer($crawler);
        $form = $this->form($crawler, ['contact[captcha]' => $answer]);

        $this->client->submit($form);
        $this->assertResponseRedirects('/contact');
        $before = $this->contactCount();

        // The new sum may by chance have the same answer: draw again until it doesn't
        while (MathCaptchaSolver::answer($this->client->request('GET', '/contact')) === $answer) {
            $this->submit($this->client->getCrawler(), ['contact[captcha]' => 'wrong']);
        }

        $this->client->submit($form);
        $this->assertResponseIsUnprocessable();
        $this->assertSame($before, $this->contactCount());
    }

    public function testFilledHoneypotSavesAndSendsNothing(): void
    {
        $before = $this->contactCount();

        $crawler = $this->client->request('GET', '/contact');
        $this->submit($crawler, [
            'contact[captcha]' => MathCaptchaSolver::answer($crawler),
            'contact[website]' => 'https://spam.example.com',
        ]);

        // Looks like success to the bot
        $this->assertResponseRedirects('/contact');
        $this->assertSame($before, $this->contactCount());
        $this->assertEmailCount(0);
    }

    /** @param array<string, string> $values */
    private function submit(Crawler $crawler, array $values): Crawler
    {
        return $this->client->submit($this->form($crawler, $values));
    }

    /** @param array<string, string> $values */
    private function form(Crawler $crawler, array $values): \Symfony\Component\DomCrawler\Form
    {
        return $crawler->filter('#contact_form')->form($values + [
            'contact[salutation]' => Contact::SALUTATION_UND,
            'contact[name]' => 'Visitor',
            'contact[email]' => 'visitor@example.com',
            'contact[subject]' => 'Hello',
            'contact[message]' => 'Hello there, a message for you',
        ]);
    }

    private function contactCount(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM contact');
    }
}
