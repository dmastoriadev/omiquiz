[CmdletBinding()]
param(
    [string]$TargetProject = $PSScriptRoot
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path -LiteralPath $TargetProject).Path.TrimEnd([char[]]'\/')
$separator = [System.IO.Path]::DirectorySeparatorChar
$comparison = [System.StringComparison]::OrdinalIgnoreCase

# Run directly inside the project after copying the updated package over it.
# All replacement files must be present before any legacy file is removed.
$expectedFiles = @{
    'app/Console/Commands/CreateAdmin.php' = '41d4060fadf9082d86880130377c3f0be38e18483417bd9ab2d8b71f826b825e'
    'app/Http/Controllers/Admin/DashboardController.php' = '565a7716e847bd2511cb83954e42cbc8a8074250b10639b8becf852ae6e91982'
    'app/Http/Controllers/Admin/UserController.php' = '292aa6357dd4a23a09934e82a907cb77f8419b02f8e147d27f590978fab3bc7e'
    'app/Http/Controllers/Auth/AuthenticatedSessionController.php' = '1428105a4711b2d335d595929458519128c823087a7f63adbc3ef8af94dcf520'
    'app/Http/Controllers/Auth/NewPasswordController.php' = 'a9cfb20370ea14769da3d0bfbedfe541097cc67a9d05e1b3c23d705457378ca9'
    'app/Http/Controllers/Auth/PasswordResetLinkController.php' = 'b02206206c289d5eaa59d00e00005c76d66ac65073e9fe784fab473e612f9513'
    'app/Http/Controllers/Controller.php' = 'f8bc4ae410eed2e2bd61c18cdebf51625e856c24e631ca63c1614e9b767c2332'
    'app/Http/Middleware/AdminRoleMiddleware.php' = '0c919c346ecf24d46e126dacf13adfbb2aa2381c010c6822795981b1a6643196'
    'app/Http/Requests/Auth/LoginRequest.php' = '020a072e8b6af59db80d8ee17a80dad509d8aa881544d42370e25aa76cace1d3'
    'app/Models/Game.php' = '036e39f76e221c2ea13df99810bd2e7ffdd06af1d308751c7f50a866615ae550'
    'app/Models/GameAnswer.php' = 'dadaa6ed5631291882935429bb05e7d808da590836d7105e27d4c0b1192f5695'
    'app/Models/GameResult.php' = 'a64cf47881cef8f14d4e5ea1f600d981fe155ad2261ab610ee24d5c4c62fa268'
    'app/Models/GameSession.php' = '648b705ccfc8c135a56dd699730beb574614efc34bfe5d2aa03d0690e777ad93'
    'app/Models/GameSessionPlayer.php' = '74264556668466e16e50ac0443cb7d3451ba611d2d99116030cca622b9f66563'
    'app/Models/GameSessionQuestion.php' = 'b74b0ea048404626ecb7a32b581dcbf0c2d672fb64fbd9a3b508e2d74fe4863b'
    'app/Models/Question.php' = 'ee921d70f693d7ebaa2e3eb3d65bc3f372c7fe8ccee52e9dfdf01f56a7070cc1'
    'app/Models/User.php' = 'c3663414fc8b131a738784d37058fcfff7a522d5a218fb663c70486368bc85d6'
    'app/Providers/AppServiceProvider.php' = '887c7418857d30552241aace3a1e33134cff1b659129c67f6efb08d9c1d1e234'
    'bootstrap/app.php' = '6e6a2cedee147b836404290bb325cc9aa9b9a3099cc78374aaa3137bcab7d909'
    'bootstrap/providers.php' = '2ef405f988a6ef3f5222bfbdd89352a6ad7b2e9e21aa25e236ac5dff58358953'
    'config/app.php' = '5e83b825652351011d58b06aa688cfd60b7f358be13357bd192aa954933c684a'
    'config/auth.php' = '50f8a6fabf848d20ea42a21e01066dd8a75bd822c8251a45f8939616432424dc'
    'config/cache.php' = '38e71cb151e7506907ee1c4a6a27d0f49acc5cd177aaac08e14041907fe16567'
    'config/database.php' = 'c6eaf6694906bfa0f7e6c6bf1857dd7d0d1a25585c15b63b0e53f31ec3c2aae9'
    'config/filesystems.php' = '3a2d2d6286081ff57275d63e0b0518a63b02dd1c1e07ddc8932df860290b2963'
    'config/logging.php' = 'd90f24826a3330437f2f2fc12bb28680abe1281d35d62b082c234f4b69873835'
    'config/mail.php' = '4a5df438dba1a007a4c89339d8decf0daba10589d773f27065c6d0c07ee71b16'
    'config/queue.php' = 'a185a3e86b65db26938204f4f50cf0c0ca99974e9926b9cdcef6b7743feea482'
    'config/services.php' = '1db24314a6eebfc35bcab23cc6cfa3cb1d42b7d75bc54fe46b2f6a23032855ad'
    'config/session.php' = '12ec2d64eaffa4a9378a7f0d49f4ffd117f798b6b60a6efd3be067e4b01f1f95'
    'database/factories/UserFactory.php' = 'a54c12179a10f07877d6bcc9415a018f5693ff3f26323eceb5b858932045c8c1'
    'database/migrations/0001_01_01_000000_create_users_table.php' = '10d79883bc08ce46510f9ad8b1830b4d06d586dc56ae2c8bac3b3e491f69a90f'
    'database/migrations/0001_01_01_000001_create_cache_table.php' = '20f769d3c11748c1cc42c41cd3789b0b9b2cd0db44ee22d378addac3ff8aed4f'
    'database/migrations/0001_01_01_000002_create_jobs_table.php' = '7c9c712adf4091c87bf2612f4d0dccdfa1df4501553e4ac068b83d7bd5462fa5'
    'database/migrations/2025_08_13_050932_add_role_to_users_table.php' = '31ac8b4634f28547697c5fe5e2bf65045a1a5ed8c97c259d02e5d478dafc4bb1'
    'database/migrations/2026_10_23_000001_create_games_table.php' = 'aa524b66338df917e0ad790040bb99198eb6f902192f4dcde8145e23d4b2efa2'
    'database/migrations/2026_10_23_000002_create_questions_table.php' = '1cc31cd90316e3826305c65feca22c8e401542947c22b93e7063d22cb106f87e'
    'database/migrations/2026_10_23_000003_create_game_sessions_table.php' = '4966becf69e27dfdf511ed291a0755e633098969c5ff8301044bd2f847ff9c4c'
    'database/migrations/2026_10_23_000004_create_game_session_questions_table.php' = 'fc66a3a1d9ab640e938f23186793e4ffa0ac6c71a5e53e07874194d20a93525d'
    'database/migrations/2026_10_23_000005_create_game_session_players_table.php' = 'a491885b45587613c165c4e2629d2ab12ca31df528fb78502fa0138f2db9bd43'
    'database/migrations/2026_10_23_000006_create_game_answers_table.php' = '22c78df2ff1d7555e4be3df24975a951ed1970527a63532ac7722bfb5bda5483'
    'database/migrations/2026_10_23_000007_create_game_results_table.php' = '3b3962579d3a63dae7ef09d3a42ab978e65a7ca10b9ec48736d83a4e028b4f5d'
    'database/seeders/DatabaseSeeder.php' = 'aab1d171bbda0f5a5696a8b93c2e52b9d4ae3b85d48487e34fa7f2b82e6eb51b'
    'resources/css/app.css' = '0b457d4ab38c537e22798c4315ce1c9d4792e9eebb66818e0ce1d388dd7eb3de'
    'resources/js/app.js' = '8a3ca259bbdcbc6765a2c20b6ae36559c1baed6d3f7d80737fa8cd1c4ca4481c'
    'resources/js/bootstrap.js' = '77ae0042f13afa9a6b8ccb1684fffcfb64ed2fd61566070c44a578cbfe328e14'
    'resources/views/admin/dashboard.blade.php' = 'b020ca95dd911c8871c3d593ff8db2d690ad0b2c91f0992f25584cb214c09f55'
    'resources/views/admin/layout.blade.php' = '717870e599a47b6e9b23351fc3899474fc34ec7b645cc5721b0817dac51af3df'
    'resources/views/admin/users/_form.blade.php' = 'c552bc62e1bdc62fd79ee1fde0882871998ee302320f81c8278c923253607754'
    'resources/views/admin/users/create.blade.php' = 'b1b3326ce95007c45e48b3918c9c9e176c305a0742a9ca2f299aab87896df8ab'
    'resources/views/admin/users/edit.blade.php' = '7a4132b674c81be9896f0d24d3b5bb26c23d7a68daa067df6250ca8b90d094bd'
    'resources/views/admin/users/index.blade.php' = 'bb483fbdef7fec7124e2103b43ad803bcbe49d3551f227f96c5226e06bae02fd'
    'resources/views/auth/forgot-password.blade.php' = 'c3374461f3eab2ed6147ca1c085dedb781e0e1003f37ab6a01a663e27f56aaf3'
    'resources/views/auth/login.blade.php' = 'fdce51db2b8e144c94661ab2e1cf9675781dab09a2ba0924a02c57b6cfec4999'
    'resources/views/auth/reset-password.blade.php' = '359a85c8e0d1a5f2bb8c42dc593a0552622ac759f6c64331e155c2e14c24994e'
    'resources/views/errors/404.blade.php' = '5ceaaf190df2bb6ab892d8fa4cf2bc4d1ffc65a6da79886b3f87806e873c0e25'
    'resources/views/layouts/guest.blade.php' = '1e2dab7df6109407567ff20279215a53369c4e5167a930d88766de7637073f2c'
    'resources/views/partials/feedback.blade.php' = 'c3a468ddafe341a859da81fda013cda0312e366b6fdc73f5b0e15d9f59a16251'
    'routes/auth.php' = 'f66f89aaa66a2a5fee79171a451d27f45b94aae367e7a3216237aeea26c2a579'
    'routes/console.php' = 'de85093043b927358fb697b8312a1e2ad9578d7b911e6206cf101f945c566233'
    'routes/web.php' = '17e9726ab486099d08e2bd0caeabbbe972607b6af9223c060e34cc4be057af74'
}
$legacyPaths = @(
    'app/Console/Commands/GenerateSitemap.php'
    'app/Console/Commands/IndexSearchDocuments.php'
    'app/Http/Controllers/AccessFormController.php'
    'app/Http/Controllers/Admin/BlogController.php'
    'app/Http/Controllers/Admin/CachePurgeController.php'
    'app/Http/Controllers/Admin/CareerApplicationAdminController.php'
    'app/Http/Controllers/Admin/CareerController.php'
    'app/Http/Controllers/Admin/CategoryController.php'
    'app/Http/Controllers/Admin/MediaController.php'
    'app/Http/Controllers/Admin/PopupController.php'
    'app/Http/Controllers/Admin/PromoCategoryController.php'
    'app/Http/Controllers/Admin/PromoController.php'
    'app/Http/Controllers/Admin/RedirectRuleController.php'
    'app/Http/Controllers/Auth/ConfirmablePasswordController.php'
    'app/Http/Controllers/Auth/EmailVerificationNotificationController.php'
    'app/Http/Controllers/Auth/EmailVerificationPromptController.php'
    'app/Http/Controllers/Auth/PasswordController.php'
    'app/Http/Controllers/Auth/RegisteredUserController.php'
    'app/Http/Controllers/Auth/VerifyEmailController.php'
    'app/Http/Controllers/BlogPublicController.php'
    'app/Http/Controllers/CareerApplicationController.php'
    'app/Http/Controllers/CareerPublicController.php'
    'app/Http/Controllers/ContactController.php'
    'app/Http/Controllers/PromoPublicController.php'
    'app/Http/Controllers/SearchController.php'
    'app/Http/Controllers/UnsubscribeController.php'
    'app/Http/Middleware/HandleRedirectRules.php'
    'app/Http/Requests/AccessFormRequest.php'
    'app/Http/Requests/ProfileUpdateRequest.php'
    'app/Http/Requests/UnsubscribeRequest.php'
    'app/Mail/CareerApplicationSubmitted.php'
    'app/Models/Article.php'
    'app/Models/ArticleCategory.php'
    'app/Models/CachePurgeLog.php'
    'app/Models/Career.php'
    'app/Models/CareerApplication.php'
    'app/Models/Category.php'
    'app/Models/Popup.php'
    'app/Models/Promo.php'
    'app/Models/PromoCategory.php'
    'app/Models/RedirectRule.php'
    'app/Models/SearchDocument.php'
    'app/Notifications/AdminResetPasswordNotification.php'
    'app/Providers/RouteServiceProvider.php'
    'app/Rules/RecaptchaV3.php'
    'app/Services/MediaServices.php'
    'app/Support/ScheduleNormalizer.php'
    'app/View/Components/AppLayout.php'
    'app/View/Components/GuestLayout.php'
    'config/activitylog.php'
    'config/promos.php'
    'database/migrations/2025_08_13_081520_create_categories_table.php'
    'database/migrations/2025_08_13_093543_create_articles_table.php'
    'database/migrations/2025_08_13_110224_add_author_and_createdby_to_articles_table.php'
    'database/migrations/2025_08_13_195034_create_article_category_table.php'
    'database/migrations/2025_08_13_195204_add_article_category_id_to_articles_table.php'
    'database/migrations/2025_08_18_192902_create_promos_table.php'
    'database/migrations/2025_08_19_155359_add_unique_to_article_categories_table.php'
    'database/migrations/2025_08_19_160413_fix_article_categories_table.php'
    'database/migrations/2025_08_19_163311_rescue_article_categories_table.php'
    'database/migrations/2025_08_20_163917_add_deleted_at_to_articles_table.php'
    'database/migrations/2025_08_22_164847_add_scheduled_publish_date_to_promos_table.php'
    'database/migrations/2025_08_22_185059_add_scheduled_publish_date_to_articles_table.php'
    'database/migrations/2025_08_23_074142_create_activity_log_table.php'
    'database/migrations/2025_08_23_074143_add_event_column_to_activity_log_table.php'
    'database/migrations/2025_08_23_074144_add_batch_uuid_column_to_activity_log_table.php'
    'database/migrations/2025_08_24_154042_alter_promos_category_enum_add_all.php'
    'database/migrations/2025_09_05_022554_alter_promos_category_enum_to_properties.php'
    'database/migrations/2025_10_12_144158_update_promos_category_enum_add_mice_fnb.php'
    'database/migrations/2025_10_12_150520_change_promos_category_to_string.php'
    'database/migrations/2025_11_25_035256_create_popups_table.php'
    'database/migrations/2025_11_25_035300_add_created_by_to_popups_table.php'
    'database/migrations/2025_11_25_035302_add_image_gallery_to_popups_table.php'
    'database/migrations/2025_11_25_055505_add_deleted_at_to_promos_table.php'
    'database/migrations/2025_12_04_014101_create_careers_table.php.php'
    'database/migrations/2025_12_04_014719_add_status_to_careers_table.php.php'
    'database/migrations/2025_12_05_001117_add_notify_emails_to_careers_table.php.php'
    'database/migrations/2025_12_05_004529_create_career_applications_table.php.php'
    'database/migrations/2025_12_05_012150_add_fields_to_career_applications_table.php.php'
    'database/migrations/2025_12_05_013649_add_cv_fields_to_career_applications_table.php.php'
    'database/migrations/2025_12_05_035941_add_applications_viewed_at_to_careers_table.php'
    'database/migrations/2026_04_23_235858_create_promo_categories_table.php'
    'database/migrations/2026_06_09_054254_create_redirect_rules_table.php'
    'database/migrations/2026_06_09_054300_create_cache_purge_logs_table.php'
    'database/migrations/2026_06_22_092042_create_search_documents_table.php'
    'database/migrations/2026_06_26_044914_add_article_category_id_to_articles_table.php.php'
    'database/migrations/2026_06_26_111716_dd_editor_tracking_to_articles_and_promos_tables.php'
    'database/migrations/2026_08_28_025009_add_tags_to_promos_table.php'
    'database/migrations/2026_09_10_120103_create_career_slug_redirects_table.php'
    'database/migrations/2026_09_11_020107_scope_career_slug_uniqueness_to_public_path.php'
    'database/migrations/2026_10_01_052718_ensure_promo_tags_column_exists.php'
    'database/seeders/AdminUserSeeder.php'
    'database/seeders/CategorySeeder.php'
    'resources/fonts/Whitney/Whitney-Black.woff'
    'resources/fonts/Whitney/Whitney-BlackItalic.woff'
    'resources/fonts/Whitney/Whitney-Bold.woff'
    'resources/fonts/Whitney/Whitney-BoldItalic.woff'
    'resources/fonts/Whitney/Whitney-Book.woff'
    'resources/fonts/Whitney/Whitney-BookItalic.woff'
    'resources/fonts/Whitney/Whitney-Light.woff'
    'resources/fonts/Whitney/Whitney-LightItalic.woff'
    'resources/fonts/Whitney/Whitney-Medium.woff'
    'resources/fonts/Whitney/Whitney-MediumItalic.woff'
    'resources/fonts/Whitney/Whitney-Semibold.woff'
    'resources/fonts/Whitney/Whitney-SemiboldItalic.woff'
    'resources/views/Properties/ab1.blade.php'
    'resources/views/Properties/abh.blade.php'
    'resources/views/Properties/ac3.blade.php'
    'resources/views/Properties/apw.blade.php'
    'resources/views/Properties/apz.blade.php'
    'resources/views/Properties/spo.blade.php'
    'resources/views/Properties/spr.blade.php'
    'resources/views/about-astoria.blade.php'
    'resources/views/admin/blogs/index.blade.php'
    'resources/views/admin/blogs/partials/table.blade.php'
    'resources/views/admin/blogs/partials/trashtable.blade.php'
    'resources/views/admin/cache-purge/index.blade.php'
    'resources/views/admin/careers/_slug-field.blade.php'
    'resources/views/admin/careers/applications/index.blade.php'
    'resources/views/admin/careers/applications/submissions.blade.php'
    'resources/views/admin/careers/create.blade.php'
    'resources/views/admin/careers/edit.blade.php'
    'resources/views/admin/careers/index.blade.php'
    'resources/views/admin/dashboard/partials/blogs_table.blade.php'
    'resources/views/admin/dashboard/partials/blogs_trashtable.blade.php'
    'resources/views/admin/dashboard/partials/popup_table.blade.php'
    'resources/views/admin/dashboard/partials/promos_table.blade.php'
    'resources/views/admin/dashboard/partials/promos_trashtable.blade.php'
    'resources/views/admin/media/index.blade.php'
    'resources/views/admin/partials/category-manager.blade.php'
    'resources/views/admin/partials/publishing-schedule.blade.php'
    'resources/views/admin/popup/create.blade.php'
    'resources/views/admin/popup/index.blade.php'
    'resources/views/admin/popup/partials/form.blade.php'
    'resources/views/admin/promos/index.blade.php'
    'resources/views/admin/promos/partials/table.blade.php'
    'resources/views/admin/promos/partials/trashtable.blade.php'
    'resources/views/admin/redirect-rules/create.blade.php'
    'resources/views/admin/redirect-rules/edit.blade.php'
    'resources/views/admin/redirect-rules/index.blade.php'
    'resources/views/articles/show.blade.php'
    'resources/views/auth/confirm-password.blade.php'
    'resources/views/auth/register.blade.php'
    'resources/views/auth/verify-email.blade.php'
    'resources/views/awards.blade.php'
    'resources/views/blogs.blade.php'
    'resources/views/careers/_list.blade.php'
    'resources/views/careers/apply.blade.php'
    'resources/views/careers/index.blade.php'
    'resources/views/careers/show.blade.php'
    'resources/views/cctv-policy.blade.php'
    'resources/views/celebrating.blade.php'
    'resources/views/components/application-logo.blade.php'
    'resources/views/components/auth-session-status.blade.php'
    'resources/views/components/cookie-consent.blade.php'
    'resources/views/components/danger-button.blade.php'
    'resources/views/components/dropdown-link.blade.php'
    'resources/views/components/dropdown.blade.php'
    'resources/views/components/faq-side.blade.php'
    'resources/views/components/footer.blade.php'
    'resources/views/components/home-blogs-promos.blade.php'
    'resources/views/components/home-brand.blade.php'
    'resources/views/components/input-error.blade.php'
    'resources/views/components/input-label.blade.php'
    'resources/views/components/modal.blade.php'
    'resources/views/components/nav-booking.blade.php'
    'resources/views/components/nav-header.blade.php'
    'resources/views/components/nav-link.blade.php'
    'resources/views/components/nav/ab1-nav.blade.php'
    'resources/views/components/nav/abh-nav.blade.php'
    'resources/views/components/nav/ac3-nav.blade.php'
    'resources/views/components/nav/apw-nav.blade.php'
    'resources/views/components/nav/apz-nav.blade.php'
    'resources/views/components/nav/spo-nav.blade.php'
    'resources/views/components/nav/spr-nav.blade.php'
    'resources/views/components/news-media.blade.php'
    'resources/views/components/popup-runner.blade.php'
    'resources/views/components/primary-button.blade.php'
    'resources/views/components/responsive-nav-link.blade.php'
    'resources/views/components/secondary-button.blade.php'
    'resources/views/components/text-input.blade.php'
    'resources/views/contact.blade.php'
    'resources/views/csr.blade.php'
    'resources/views/dining.blade.php'
    'resources/views/emails/access-form-notification.blade.php'
    'resources/views/emails/auth/reset-password.blade.php'
    'resources/views/emails/careers/application.blade.php'
    'resources/views/emails/contact-plain.blade.php'
    'resources/views/emails/unsubscribe-notification.blade.php'
    'resources/views/faqs.blade.php'
    'resources/views/forms/access-form.blade.php'
    'resources/views/forms/unsubscribe.blade.php'
    'resources/views/home.blade.php'
    'resources/views/layouts/app.blade.php'
    'resources/views/layouts/navigation.blade.php'
    'resources/views/meetings.blade.php'
    'resources/views/menus.blade.php'
    'resources/views/pop-up/show.blade.php'
    'resources/views/privacy-policy.blade.php'
    'resources/views/profile/edit.blade.php'
    'resources/views/profile/partials/delete-user-form.blade.php'
    'resources/views/profile/partials/update-password-form.blade.php'
    'resources/views/profile/partials/update-profile-information-form.blade.php'
    'resources/views/promos.blade.php'
    'resources/views/promos/show.blade.php'
    'resources/views/search.blade.php'
    'resources/views/sections/ab1-rooms-suites.blade.php'
    'resources/views/sections/abh-rooms-suites.blade.php'
    'resources/views/sections/ac3-rooms-suites.blade.php'
    'resources/views/sections/apw-rooms-suites.blade.php'
    'resources/views/sections/apz-rooms-suites.blade.php'
    'resources/views/sections/spo-rooms-suites.blade.php'
    'resources/views/sections/spr-rooms-suites.blade.php'
    'resources/views/welcome.blade.php'
    'database/migrations/omiquiz/2026_10_23_000001_create_games_table.php'
    'database/migrations/omiquiz/2026_10_23_000002_create_questions_table.php'
    'database/migrations/omiquiz/2026_10_23_000003_create_game_sessions_table.php'
    'database/migrations/omiquiz/2026_10_23_000004_create_game_session_questions_table.php'
    'database/migrations/omiquiz/2026_10_23_000005_create_game_session_players_table.php'
    'database/migrations/omiquiz/2026_10_23_000006_create_game_answers_table.php'
    'database/migrations/omiquiz/2026_10_23_000007_create_game_results_table.php'
    'bootstrap/cache/config.php'
    'bootstrap/cache/services.php'
    'bootstrap/cache/packages.php'
    'bootstrap/cache/events.php'
    'bootstrap/cache/routes-v7.php'
)

function Get-SafeProjectFile([string]$RelativePath) {
    $parts = $RelativePath -split '[\\/]'
    if ([System.IO.Path]::IsPathRooted($RelativePath) -or $RelativePath.Contains(':') -or
        $parts -contains '..' -or $parts -contains '.' -or $parts -contains '') {
        throw "Invalid relative path: $RelativePath"
    }
    $candidate = [System.IO.Path]::GetFullPath((Join-Path $projectRoot ($parts -join $separator)))
    if (-not $candidate.StartsWith($projectRoot + $separator, $comparison)) {
        throw "Path is outside this project: $RelativePath"
    }
    $walk = $projectRoot
    foreach ($part in @('') + $parts) {
        if ($part -ne '') { $walk = Join-Path $walk $part }
        $item = Get-Item -LiteralPath $walk -Force -ErrorAction SilentlyContinue
        if ($null -ne $item -and ($item.Attributes -band [System.IO.FileAttributes]::ReparsePoint)) {
            throw "Refusing to follow a linked file or directory: $walk"
        }
    }
    return $candidate
}

foreach ($required in @('artisan', 'composer.json')) {
    if (-not (Test-Path -LiteralPath (Get-SafeProjectFile $required) -PathType Leaf)) {
        throw "Run this script in your existing Laravel project. Missing: $required"
    }
}

$sha = [System.Security.Cryptography.SHA256]::Create()
try {
    foreach ($relative in $expectedFiles.Keys) {
        $path = Get-SafeProjectFile $relative
        if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
            throw "Missing updated file: $relative. Copy the updated ZIP contents into this project, then run again."
        }
        $text = [System.IO.File]::ReadAllText($path).Replace("`r`n", "`n")
        $hash = [BitConverter]::ToString($sha.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($text))).Replace('-', '').ToLowerInvariant()
        if ($hash -ne $expectedFiles[$relative]) {
            throw "Updated file does not match: $relative. Copy the updated ZIP contents into this project, then run again. No files have been removed."
        }
    }
} finally {
    $sha.Dispose()
}

$files = [System.Collections.Generic.List[object]]::new()
foreach ($relative in $legacyPaths) {
    $path = Get-SafeProjectFile $relative
    if (Test-Path -LiteralPath $path) {
        if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
            throw "Expected a file at $relative. Nothing has been removed."
        }
        $files.Add([PSCustomObject]@{
            Relative = $relative
            FullPath = $path
            Hash = (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash
        })
    }
}

if ($files.Count -eq 0) {
    Write-Host 'The omiquiz files are already clean. No legacy files were found.'
    exit 0
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$suffix = [guid]::NewGuid().ToString('N').Substring(0, 8)
$backupPath = Join-Path ([System.IO.Path]::GetTempPath()) ("omiquiz-removed-files-$stamp-$suffix.zip")
$stream = [System.IO.File]::Open($backupPath, [System.IO.FileMode]::CreateNew, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::None)
$archive = [System.IO.Compression.ZipArchive]::new($stream, [System.IO.Compression.ZipArchiveMode]::Create, $false)
try {
    foreach ($file in $files) {
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $file.FullPath, $file.Relative) | Out-Null
    }
} finally {
    $archive.Dispose()
}

# Verify every backed-up byte before deleting the first legacy file.
$archive = [System.IO.Compression.ZipFile]::OpenRead($backupPath)
$sha = [System.Security.Cryptography.SHA256]::Create()
try {
    foreach ($file in $files) {
        $entry = $archive.GetEntry($file.Relative)
        if ($null -eq $entry) { throw "Backup verification failed for $($file.Relative). Nothing was removed." }
        $entryStream = $entry.Open()
        try {
            $hash = [BitConverter]::ToString($sha.ComputeHash($entryStream)).Replace('-', '')
        } finally { $entryStream.Dispose() }
        if ($hash -ne $file.Hash) { throw "File changed during backup: $($file.Relative). Nothing was removed." }
    }
} finally {
    $sha.Dispose()
    $archive.Dispose()
}

$removed = [System.Collections.Generic.List[object]]::new()
try {
    foreach ($file in $files) {
        $path = Get-SafeProjectFile $file.Relative
        if ((Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash -ne $file.Hash) {
            throw "File changed after backup: $($file.Relative). Cleanup stopped."
        }
        Remove-Item -LiteralPath $path -Force
        $removed.Add($file)
    }
} catch {
    $cleanupError = $_
    $archive = [System.IO.Compression.ZipFile]::OpenRead($backupPath)
    try {
        foreach ($file in $removed) {
            try {
                $path = Get-SafeProjectFile $file.Relative
                if (-not (Test-Path -LiteralPath $path)) {
                    [System.IO.Compression.ZipFileExtensions]::ExtractToFile($archive.GetEntry($file.Relative), $path, $false)
                }
            } catch { Write-Warning "Could not restore $($file.Relative); recover it from $backupPath." }
        }
    } finally { $archive.Dispose() }
    Write-Warning "Cleanup stopped. Removed files were restored wherever possible. Backup: $backupPath"
    throw $cleanupError
}

Write-Host "Removed $($removed.Count) obsolete files from $projectRoot."
Write-Host "Backup of removed files: $backupPath"
Write-Host 'Your environment, dependencies, public files, storage, and database were not changed.'
Write-Host 'Continue setup using README-OMIQUIZ.md.'
