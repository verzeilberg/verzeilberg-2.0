<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Entity\User;
use App\Entity\UserDetails;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/** The edit profile form: what a user may change about themselves (not their email, password or roles) */
class ProfileFormModel
{
    /** Social platforms a user can link to; the keys are Font Awesome brand icon names */
    public const SOCIAL_PLATFORMS = [
        'github' => 'GitHub',
        'linkedin' => 'LinkedIn',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'x-twitter' => 'X',
        'youtube' => 'YouTube',
        'strava' => 'Strava',
        'steam' => 'Steam',
    ];

    #[Assert\NotBlank(message: 'Vul je voornaam in')]
    #[Assert\Length(max: 50)]
    public ?string $firstName = null;

    #[Assert\NotBlank(message: 'Vul je achternaam in')]
    #[Assert\Length(max: 50)]
    public ?string $lastName = null;

    #[Assert\Length(max: 255)]
    public ?string $catchLine = null;

    #[Assert\Length(max: 5000)]
    public ?string $bio = null;

    #[Assert\Length(max: 100)]
    public ?string $location = null;

    #[Assert\Url(requireTld: true, message: 'Vul een volledig webadres in, beginnend met https://')]
    #[Assert\Length(max: 255)]
    public ?string $website = null;

    #[Assert\Length(max: 30)]
    #[Assert\Regex(pattern: '/^[0-9+()\-\s]*$/', message: 'Een telefoonnummer mag alleen cijfers, spaties en + ( ) - bevatten')]
    public ?string $phone = null;

    #[Assert\LessThan('today', message: 'Je geboortedatum moet in het verleden liggen')]
    public ?\DateTimeImmutable $birthDate = null;

    /** @var array<string, ?string> platform => profile URL */
    #[Assert\All([
        new Assert\Url(requireTld: true, message: 'Vul een volledig webadres in, beginnend met https://'),
        new Assert\Length(max: 255),
    ])]
    public array $socialLinks = [];

    #[Assert\Image(
        maxSize: '4M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        mimeTypesMessage: 'Upload een JPG-, PNG-, WebP- of GIF-afbeelding',
    )]
    public ?UploadedFile $avatarFile = null;

    public bool $removeAvatar = false;

    /** Mail about replies to the user's comments and about friend requests */
    public bool $emailNotifications = true;

    public static function fromUser(User $user): self
    {
        $model = new self();
        $model->emailNotifications = $user->wantsEmailNotifications();
        $details = $user->getDetails();

        if ($details === null) {
            // Accounts made in the admin have no details yet: start from the full name
            [$model->firstName, $model->lastName] = array_pad(explode(' ', $user->getFullName(), 2), 2, null);
            return $model;
        }

        $model->firstName = $details->getFirstName();
        $model->lastName = $details->getLastName();
        $model->catchLine = $details->getCatchLine();
        $model->bio = $details->getBio();
        $model->location = $details->getLocation();
        $model->website = $details->getWebsite();
        $model->phone = $details->getPhone();
        $model->birthDate = $details->getBirthDate();
        $model->socialLinks = $details->getSocialLinks() ?? [];

        return $model;
    }

    /** Copies everything but the avatar onto the user; the controller handles the upload */
    public function applyTo(User $user, UserDetails $details): void
    {
        $user->setFullName(trim($this->firstName) . ' ' . trim($this->lastName));
        $user->setEmailNotifications($this->emailNotifications);

        $details->setFirstName(trim($this->firstName));
        $details->setLastName(trim($this->lastName));
        $details->setCatchLine(self::clean($this->catchLine));
        $details->setBio(self::clean($this->bio));
        $details->setLocation(self::clean($this->location));
        $details->setWebsite(self::clean($this->website));
        $details->setPhone(self::clean($this->phone));
        $details->setBirthDate($this->birthDate);

        // Only known platforms with a URL, in the order of SOCIAL_PLATFORMS
        $links = [];
        foreach (array_keys(self::SOCIAL_PLATFORMS) as $platform) {
            $url = self::clean($this->socialLinks[$platform] ?? null);
            if ($url !== null) {
                $links[$platform] = $url;
            }
        }
        $details->setSocialLinks($links ?: null);
    }

    private static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }
}
