<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function findByKey(string $key): ?Setting
    {
        return $this->findOneBy(['key' => $key]);
    }

    public function getValue(string $key, $default = null)
    {
        $setting = $this->findByKey($key);
        return $setting ? $setting->getValue() : $default;
    }

    public function getBooleanValue(string $key, bool $default = false): bool
    {
        $setting = $this->findByKey($key);
        return $setting ? $setting->getBooleanValue() : $default;
    }

    public function setValue(string $key, string $value): Setting
    {
        $setting = $this->findByKey($key);
        if (!$setting) {
            $setting = new Setting();
            $setting->setKey($key);
            $this->getEntityManager()->persist($setting);
        }
        $setting->setValue($value);
        $this->getEntityManager()->flush();
        return $setting;
    }
}