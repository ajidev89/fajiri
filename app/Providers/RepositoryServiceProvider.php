<?php

namespace App\Providers;

use App\Http\Repository\AmbassadorRepository;
use App\Http\Repository\AnalyticsRepository;
use App\Http\Repository\AuthRepository;
use App\Http\Repository\CampaignRepository;
use App\Http\Repository\CategoryRepository;
use App\Http\Repository\Contracts\AmbassadorRepositoryInterface;
use App\Http\Repository\Contracts\AnalyticsRepositoryInterface;
use App\Http\Repository\Contracts\AuthRepositoryInterface;
use App\Http\Repository\Contracts\CampaignRepositoryInterface;
use App\Http\Repository\Contracts\CategoryRepositoryInterface;
use App\Http\Repository\Contracts\CountryRepositoryInterface;
use App\Http\Repository\Contracts\DisbursementRepositoryInterface;
use App\Http\Repository\Contracts\DonationRepositoryInterface;
use App\Http\Repository\Contracts\EventRepositoryInterface;
use App\Http\Repository\Contracts\FamilyMemberRepositoryInterface;
use App\Http\Repository\Contracts\FundraiserRepositoryInterface;
use App\Http\Repository\Contracts\GoogleRepositoryInterface;
use App\Http\Repository\Contracts\InitiativeRepositoryInterface;
use App\Http\Repository\Contracts\InsuranceRepositoryInterface;
use App\Http\Repository\Contracts\KycRepositoryInterface;
use App\Http\Repository\Contracts\MediaRepositoryInterface;
use App\Http\Repository\Contracts\NeedRepositoryInterface;
use App\Http\Repository\Contracts\NotificationRepositoryInterface;
use App\Http\Repository\Contracts\OtpRepositoryInterface;
use App\Http\Repository\Contracts\PartnerRepositoryInterface;
use App\Http\Repository\Contracts\PaymentRepositoryInterface;
use App\Http\Repository\Contracts\PlanRepositoryInterface;
use App\Http\Repository\Contracts\PollRepositoryInterface;
use App\Http\Repository\Contracts\PostRepositoryInterface;
use App\Http\Repository\Contracts\PreferenceRepositoryInterface;
use App\Http\Repository\Contracts\TestimonyRepositoryInterface;
use App\Http\Repository\Contracts\UserRepositoryInterface;
use App\Http\Repository\Contracts\UsersRepositoryInterface;
use App\Http\Repository\Contracts\WithdrawalRepositoryInterface;
use App\Http\Repository\CountryRepository;
use App\Http\Repository\DisbursementRepository;
use App\Http\Repository\DonationRepository;
use App\Http\Repository\EventRepository;
use App\Http\Repository\FamilyMemberRepository;
use App\Http\Repository\FundraiserRepository;
use App\Http\Repository\GoogleRepository;
use App\Http\Repository\InitiativeRepository;
use App\Http\Repository\InsuranceRepository;
use App\Http\Repository\KycRepository;
use App\Http\Repository\MediaRepository;
use App\Http\Repository\NeedRepository;
use App\Http\Repository\NotificationRepository;
use App\Http\Repository\OtpRepository;
use App\Http\Repository\PartnerRepository;
use App\Http\Repository\PaymentRepository;
use App\Http\Repository\PlanRepository;
use App\Http\Repository\PollRepository;
use App\Http\Repository\PostRepository;
use App\Http\Repository\PreferenceRepository;
use App\Http\Repository\TestimonyRepository;
use App\Http\Repository\UserRepository;
use App\Http\Repository\UsersRepository;
use App\Http\Repository\WithdrawalRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public $bindings = [
        CountryRepositoryInterface::class => CountryRepository::class,
        AuthRepositoryInterface::class => AuthRepository::class,
        OtpRepositoryInterface::class => OtpRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        KycRepositoryInterface::class => KycRepository::class,
        GoogleRepositoryInterface::class => GoogleRepository::class,
        CampaignRepositoryInterface::class => CampaignRepository::class,
        DonationRepositoryInterface::class => DonationRepository::class,
        PaymentRepositoryInterface::class => PaymentRepository::class,
        NotificationRepositoryInterface::class => NotificationRepository::class,
        PlanRepositoryInterface::class => PlanRepository::class,
        InitiativeRepositoryInterface::class => InitiativeRepository::class,
        PreferenceRepositoryInterface::class => PreferenceRepository::class,
        InsuranceRepositoryInterface::class => InsuranceRepository::class,
        NeedRepositoryInterface::class => NeedRepository::class,
        UsersRepositoryInterface::class => UsersRepository::class,
        AnalyticsRepositoryInterface::class => AnalyticsRepository::class,
        WithdrawalRepositoryInterface::class => WithdrawalRepository::class,
        CategoryRepositoryInterface::class => CategoryRepository::class,
        PostRepositoryInterface::class => PostRepository::class,
        EventRepositoryInterface::class => EventRepository::class,
        PartnerRepositoryInterface::class => PartnerRepository::class,
        DisbursementRepositoryInterface::class => DisbursementRepository::class,
        FundraiserRepositoryInterface::class => FundraiserRepository::class,
        MediaRepositoryInterface::class => MediaRepository::class,
        FamilyMemberRepositoryInterface::class => FamilyMemberRepository::class,
        PollRepositoryInterface::class => PollRepository::class,
        AmbassadorRepositoryInterface::class => AmbassadorRepository::class,
        TestimonyRepositoryInterface::class => TestimonyRepository::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {}
}
