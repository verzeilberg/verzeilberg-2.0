<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SearchPhrase;
use App\Repository\SearchPhraseRepository;
use Doctrine\ORM\EntityManagerInterface;

class SearchPhraseService
{
    public function __construct(
        private SearchPhraseRepository $searchPhraseRepository,
        private EntityManagerInterface $em
    ) {
    }

    public function trackSearch(string $searchPhrase, string $type = 'blog'): ?SearchPhrase
    {
        $searchPhrase = trim(strtolower($searchPhrase));
        
        if (empty($searchPhrase)) {
            return null;
        }

        $existing = $this->searchPhraseRepository->findByPhrase($searchPhrase);

        if ($existing) {
            $existing->incrementCount();
            $existing->updateDateSearched();
            $this->em->flush();
            return $existing;
        }

        $newPhrase = new SearchPhrase();
        $newPhrase->setSearchPhrase($searchPhrase);
        $newPhrase->setType($type);
        $newPhrase->setCount(1);

        $this->em->persist($newPhrase);
        $this->em->flush();

        return $newPhrase;
    }

    public function deletePhrase(int $id): bool
    {
        $phrase = $this->searchPhraseRepository->find($id);
        
        if (!$phrase) {
            return false;
        }

        $this->em->remove($phrase);
        $this->em->flush();
        
        return true;
    }

    public function getAllPhrases(int $page = 1, int $limit = 10)
    {
        return $this->searchPhraseRepository->findAllOrdered($page, $limit);
    }
}