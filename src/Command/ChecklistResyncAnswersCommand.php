<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CheckListItem;
use App\Service\CheckListService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:checklist:resync-answers',
    description: 'Rebuild the AnswerGiven records of all checklist items from their itemContent',
)]
class ChecklistResyncAnswersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private CheckListService $checkListService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $items = $this->em->getRepository(CheckListItem::class)->findAll();

        foreach ($items as $item) {
            $this->checkListService->syncItemAnswers($item, $item->getCheckList());
        }

        $output->writeln(sprintf('<info>Rebuilt the answers of %d checklist item(s).</info>', count($items)));

        return Command::SUCCESS;
    }
}
