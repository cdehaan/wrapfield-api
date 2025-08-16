<?php
    require_once "CreateDatabaseConnection.php";

    $returnData = [];

    // Start - Data from the player refreshing connection
    $userInput = json_decode(file_get_contents("php://input"));

    // Validate required inputs
    if(!isset($userInput->playerKey)) { 
        $returnData['error'] = 'No player key provided for connection refresh.'; 
        die(json_encode($returnData)); 
    }

    if(!isset($userInput->peerId)) { 
        $returnData['error'] = 'No peer ID provided for connection refresh.'; 
        die(json_encode($returnData)); 
    }

    $playerKey = intval($userInput->playerKey);
    $peerId = substr(preg_replace("/[^A-Za-z0-9 -]/", '', $userInput->peerId), 0, 50);

    // Optional player name update
    $playerName = null;
    if(isset($userInput->playerName) && is_string($userInput->playerName)) {
        $playerName = substr(preg_replace('/[^\\p{L} 0-9]/mu', '-', $userInput->playerName), 0, 19);
    }

    // Verify player exists
    $sql = "SELECT name FROM player WHERE player_key = $playerKey;";
    $result = $conn->query($sql);
    if($result->num_rows === 0) { 
        $returnData['error'] = 'Player not found for connection refresh.'; 
        die(json_encode($returnData)); 
    }

    // Update player's peer ID and optionally name
    if($playerName !== null) {
        $sql = "UPDATE player SET peer_id = '$peerId', name = '$playerName' WHERE player_key = $playerKey;";
    } else {
        $sql = "UPDATE player SET peer_id = '$peerId' WHERE player_key = $playerKey;";
    }

    if ($conn->query($sql) !== TRUE) { 
        $returnData['error'] = 'Error updating player connection data.'; 
        die(json_encode($returnData)); 
    }

    // Find which board this player is connected to
    $sql = "SELECT board_key FROM connection WHERE player_key = $playerKey;";
    $result = $conn->query($sql);
    if($result->num_rows === 0) { 
        $returnData['error'] = 'Player is not connected to any board.'; 
        die(json_encode($returnData)); 
    }
    $boardKey = intval($result->fetch_row()[0]);

    // Get all other players connected to the same board (excluding current player)
    $sql = "SELECT player_key AS playerKey, name, peer_id AS peerId, active 
            FROM player 
            WHERE player_key IN (
                SELECT player_key FROM connection WHERE board_key = $boardKey
            ) 
            AND player_key != $playerKey;";
    
    $result = $conn->query($sql);
    $connectedPlayers = [];
    
    if($result->num_rows > 0) {
        $players = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($players as $player) {
            // Only return players with valid peer IDs
            if(!empty($player['peerId'])) {
                $connectedPlayers[] = [
                    'playerKey' => intval($player['playerKey']),
                    'name' => $player['name'],
                    'peerId' => $player['peerId'],
                    'active' => $player['active'] === '1' ? true : false
                ];
            }
        }
    }

    // Return the list of connected peers
    $returnData['success'] = true;
    $returnData['connectedPlayers'] = $connectedPlayers;
    $returnData['boardKey'] = $boardKey;

    // Return updated player info
    $returnData['player'] = [
        'playerKey' => $playerKey,
        'peerId' => $peerId
    ];

    if($playerName !== null) {
        $returnData['player']['name'] = $playerName;
    }

    echo json_encode($returnData);
?>