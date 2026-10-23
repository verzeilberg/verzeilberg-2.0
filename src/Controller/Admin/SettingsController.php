<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Setting;
use App\Form\SettingType;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/settings')]
class SettingsController extends AbstractController
{
    public function __construct(
        private SettingRepository $settingRepository,
        private EntityManagerInterface $em
    ) {}

    #[Route('/', name: 'admin_settings_index', methods: ['GET'])]
    public function index(): Response
    {
        $settings = $this->settingRepository->findAll();
        return $this->render('admin/settings/index.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/comment-moderation', name: 'admin_settings_comment_moderation', methods: ['GET', 'POST'])]
    public function commentModeration(Request $request): Response
    {
        $setting = $this->settingRepository->findByKey(Setting::COMMENT_MODERATION);
        
        if (!$setting) {
            $setting = new Setting();
            $setting->setKey(Setting::COMMENT_MODERATION);
            $setting->setDescription('Of reacties eerst goedgekeurd moeten worden voordat ze verschijnen');
            $setting->setType('boolean');
            $setting->setValue('1');
            $this->em->persist($setting);
            $this->em->flush();
        }

        $form = $this->createForm(SettingType::class, $setting, [
            'setting_type' => $setting->getType(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Instelling voor reactiemoderatie opgeslagen.');
            return $this->redirectToRoute('admin_settings_comment_moderation');
        }

        return $this->render('admin/settings/comment_moderation.html.twig', [
            'form' => $form->createView(),
            'setting' => $setting,
        ]);
    }
}