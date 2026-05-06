// ─── HANDLE POSTS ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['_tok'] ?? '') !== $tok) {
        http_response_code(403); exit('Forbidden');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_auction') {
        $name = trim($_POST['name'] ?? '');
        $date = trim($_POST['date'] ?? '');
        if ($name !== '' && $date !== '') {
            if ($date > date('Y-m-d')) {
                // Future dates not allowed
                header('Location: index.php?tab=dashboard');
                exit;
            }
            $expiresAt = date('Y-m-d', strtotime('+14 days'));
            $stmt = $db->prepare("INSERT INTO auction (user_id, name, date, expires_at) VALUES (?,?,?,?)");
            $stmt->execute([$userId, $name, $date, $expiresAt]);
            $newId = (int)$db->lastInsertId();
            $_SESSION['auction_id'] = $newId;
            logActivity($db, $userId, 'auction.create', 'auction', $newId, "Created auction: " . $name);
        }
    }

    elseif ($action === 'delete_auction') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM auction WHERE id=? AND user_id=?")->execute([$id, $userId]);
        logActivity($db, $userId, 'auction.delete', 'auction', $id, "Deleted auction ID: " . $id);
        unset($_SESSION['auction_id']);
    }

    elseif ($action === 'save_auction') {
        $stmt = $db->prepare("UPDATE auction SET name=?, date=?, commission_fee=? WHERE id=? AND user_id=?");
        $stmt->execute([trim($_POST['name']), trim($_POST['date']), (float)($_POST['commissionFee'] ?? 3.00), $activeAuctionId, $userId]);
        logActivity($db, $userId, 'auction.update', 'auction', $activeAuctionId, "Updated auction: " . trim($_POST['name']));
    }

    elseif ($action === 'add_member') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $dup = $db->prepare("SELECT id FROM members WHERE user_id=? AND name=?");
            $dup->execute([$userId, $name]);
            if (!$dup->fetch()) {
                $stmt = $db->prepare("INSERT INTO members (user_id, name, phone, email) VALUES (?,?,?,?)");
                $stmt->execute([$userId, $name, trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? '')]);
                logActivity($db, $userId, 'member.add', 'member', (int)$db->lastInsertId(), "Added member: " . $name);
            }
        }
    }

    elseif ($action === 'update_member') {
        $id   = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $db->prepare("UPDATE members SET name=?, phone=?, email=? WHERE id=? AND user_id=?");
            $stmt->execute([$name, trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? ''), $id, $userId]);
            logActivity($db, $userId, 'member.update', 'member', $id, "Updated member: " . $name);
        }
    }

    elseif ($action === 'remove_member') {
        $stmt = $db->prepare("DELETE FROM members WHERE id=? AND user_id=?");
        $stmt->execute([(int)$_POST['id'], $userId]);
        logActivity($db, $userId, 'member.remove', 'member', (int)$_POST['id'], "Removed member ID: " . (int)$_POST['id']);
    }

    elseif ($action === 'add_vehicle') {
        $memberId = (int)($_POST['memberId'] ?? 0);
        $make     = trim($_POST['make'] ?? '');
        if ($memberId && $make !== '') {
            $stmt = $db->prepare("INSERT INTO vehicles (auction_id, member_id, make, model, lot, sold_price, recycle_fee, listing_fee, sold_fee, nagare_fee, sold) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $activeAuctionId, $memberId, $make,
                trim($_POST['model']    ?? ''),
                trim($_POST['lot']      ?? ''),
                (float)($_POST['soldPrice'] ?? 0),
                (float)($_POST['recycleFee'] ?? 0),
                (float)($_POST['listingFee'] ?? 0),
                (float)($_POST['soldFee'] ?? 0),
                (float)($_POST['nagareFee'] ?? 0),
                isset($_POST['sold']) ? 1 : 0,
            ]);
            logActivity($db, $userId, 'vehicle.add', 'vehicle', (int)$db->lastInsertId(), "Added vehicle: " . $make . " " . trim($_POST['model'] ?? '') . " lot " . trim($_POST['lot'] ?? ''));
        }
    }

    elseif ($action === 'update_vehicle') {
        $id   = (int)$_POST['id'];
        $make = trim($_POST['make'] ?? '');
        if ($make !== '') {
            $stmt = $db->prepare("UPDATE vehicles SET member_id=?, make=?, model=?, lot=?, sold_price=?, recycle_fee=?, listing_fee=?, sold_fee=?, nagare_fee=?, sold=? WHERE id=? AND auction_id IN (SELECT id FROM auction WHERE user_id=?)");
            $stmt->execute([
                (int)($_POST['memberId'] ?? 0),
                $make,
                trim($_POST['model'] ?? ''),
                trim($_POST['lot']  ?? ''),
                (float)($_POST['soldPrice'] ?? 0),
                (float)($_POST['recycleFee'] ?? 0),
                (float)($_POST['listingFee'] ?? 0),
                (float)($_POST['soldFee'] ?? 0),
                (float)($_POST['nagareFee'] ?? 0),
                isset($_POST['sold']) ? 1 : 0,
                $id,
                $userId,
            ]);
            logActivity($db, $userId, 'vehicle.update', 'vehicle', $id, "Updated vehicle ID: " . $id);
        }
    }

    elseif ($action === 'remove_vehicle') {
        $stmt = $db->prepare("DELETE FROM vehicles WHERE id=? AND auction_id IN (SELECT id FROM auction WHERE user_id=?)");
        $stmt->execute([(int)$_POST['id'], $userId]);
        logActivity($db, $userId, 'vehicle.delete', 'vehicle', (int)$_POST['id'], "Deleted vehicle ID: " . (int)$_POST['id']);
    }

    elseif ($action === 'toggle_sold') {
        $stmt = $db->prepare("UPDATE vehicles SET sold = NOT sold WHERE id=? AND auction_id IN (SELECT id FROM auction WHERE user_id=?)");
        $stmt->execute([(int)$_POST['id'], $userId]);
        logActivity($db, $userId, 'vehicle.sold', 'vehicle', (int)$_POST['id'], "Toggled sold status vehicle ID: " . (int)$_POST['id']);
    }


    $tab = $_POST['tab'] ?? 'dashboard';
    header("Location: index.php?tab=$tab");
    exit;
}
