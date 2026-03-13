<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
	require_once __DIR__ . '/vendor/autoload.php';
}
require_once __DIR__ . '/app/Core/JsonResponse.php';
require_once __DIR__ . '/app/Support/Csrf.php';
require_once __DIR__ . '/app/Modules/Auth/Actions/LoginAction.php';
require_once __DIR__ . '/app/Modules/Auth/Actions/RegisterAction.php';
require_once __DIR__ . '/app/Modules/Favorites/Actions/ToggleFavoriteAction.php';
require_once __DIR__ . '/app/Modules/Favorites/Actions/ClearFavoritesAction.php';
require_once __DIR__ . '/app/Modules/Reviews/Actions/SubmitUserReviewAction.php';
require_once __DIR__ . '/app/Modules/Reviews/Actions/SubmitListingReviewAction.php';
require_once __DIR__ . '/app/Modules/Reviews/Actions/GetListingReviewsAction.php';
require_once __DIR__ . '/app/Modules/Users/Actions/GetAvatarAction.php';
require_once __DIR__ . '/app/Modules/Users/Actions/UpdateBioAction.php';
require_once __DIR__ . '/app/Modules/Users/Actions/ChangePasswordAction.php';
require_once __DIR__ . '/app/Modules/Users/Actions/UpdateProfileAction.php';
require_once __DIR__ . '/app/Modules/Users/Services/ProfilePageService.php';
require_once __DIR__ . '/app/Modules/Chat/Services/ChatService.php';
require_once __DIR__ . '/app/Modules/Chat/Services/ChatRealtimeNotifier.php';
require_once __DIR__ . '/app/Modules/Chat/Actions/GetConversationsAction.php';
require_once __DIR__ . '/app/Modules/Chat/Actions/GetMessagesAction.php';
require_once __DIR__ . '/app/Modules/Chat/Actions/SendMessageAction.php';
require_once __DIR__ . '/app/Modules/Chat/Actions/EditMessageAction.php';
require_once __DIR__ . '/app/Modules/Chat/Actions/DeleteMessageAction.php';
require_once __DIR__ . '/app/Modules/Chat/Actions/StreamChatAction.php';
require_once __DIR__ . '/app/Modules/Listings/Actions/GetListingAction.php';
require_once __DIR__ . '/app/Modules/Listings/Actions/DeleteListingAction.php';
require_once __DIR__ . '/app/Modules/Listings/Actions/SaveListingAction.php';
require_once __DIR__ . '/app/Modules/Listings/Services/ProfileHousingService.php';
require_once __DIR__ . '/app/Modules/Listings/Services/ProposalsPageService.php';
require_once __DIR__ . '/app/Modules/Listings/Services/HomePageService.php';
require_once __DIR__ . '/app/Modules/Listings/Services/ListingsApiService.php';
require_once __DIR__ . '/app/Modules/Admin/Support/AdminAccess.php';
require_once __DIR__ . '/app/Modules/Admin/Services/AdminVerificationService.php';
require_once __DIR__ . '/app/Modules/Admin/Actions/CreateInitialAdminAction.php';
require_once __DIR__ . '/app/Modules/Admin/Actions/SubmitVerificationRequestAction.php';
require_once __DIR__ . '/app/Modules/Admin/Actions/GetVerificationDocumentAction.php';
require_once __DIR__ . '/app/Modules/Admin/Actions/ModerateVerificationAction.php';
require_once __DIR__ . '/app/Modules/Admin/Actions/ManualVerificationAction.php';
require_once __DIR__ . '/app/Modules/Support/Services/SupportService.php';
require_once __DIR__ . '/app/Modules/Support/Actions/SubmitSupportRequestAction.php';

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
