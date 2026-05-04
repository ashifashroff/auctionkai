<?php
require_once __DIR__ . '/../models/AuctionModel.php';
require_once __DIR__ . '/../models/MemberModel.php';
require_once __DIR__ . '/../models/VehicleModel.php';
require_once __DIR__ . '/../models/SettingsModel.php';
require_once __DIR__ . '/../models/PaymentModel.php';
require_once __DIR__ . '/../models/MemberFeesModel.php';

function initModels(PDO $db, int $userId): array {
    return [
        'auction'    => new AuctionModel($db, $userId),
        'member'     => new MemberModel($db, $userId),
        'vehicle'    => new VehicleModel($db, $userId),
        'settings'   => new SettingsModel($db),
        'payment'    => new PaymentModel($db),
        'memberFees' => new MemberFeesModel($db),
    ];
}
