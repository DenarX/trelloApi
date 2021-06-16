<?php
$trello = new Trello;
if (isset($_GET['models'])) {
    die('<pre>' . print_r($trello->getAllTokens(), true) . '</pre>');
}
$trello->renderActoins();

/*
Generate key to config
https://trello.com/app-key
*/
class Trello
{
    function __construct()
    {
        $this->config = include 'conf.php';
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [CURLOPT_RETURNTRANSFER => 1]);
    }
    function api($method = "GET", $opt = [])
    {
        switch ($method) {
            case "POST":
                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "POST");
                break;
            case "PUT":
                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "PUT");
                break;
            case "DELETE":
                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
            default:
                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "GET");
                break;
        }
        curl_setopt_array($this->ch, $opt);
        $r = curl_exec($this->ch);
        return $r;
    }
    function createWH($callbackURL = 'http://www.mywebsite.com/trelloCallback', $description = 'test')
    {
        $params = ['key' => $this->config['key'], 'callbackURL' => $callbackURL, 'idModel' => $this->config['idModel'], 'description' => $description];
        $opt = [
            CURLOPT_URL => "https://api.trello.com/1/tokens/{$this->config['token']}/webhooks/?key={$this->config['key']}",
            CURLOPT_POSTFIELDS => $params,
        ];
        return $this->api("POST", $opt);
    }
    function deleteToken($webhookId)
    {
        return $this->api('DELETE', [CURLOPT_URL => "https://api.trello.com/1/webhooks/$webhookId?key={$this->config['key']}&token={$this->config['token']}"]);
    }
    function getAllTokens()
    {
        return $this->api('GET', [CURLOPT_URL => "https://api.trello.com/1/members/me/tokens?webhooks=true&key={$this->config['key']}&token={$this->config['token']}"]);
    }
    /**
     * https://developer.atlassian.com/cloud/trello/rest/api-group-organizations/#api-organizations-id-actions-get
     * GET /1/organizations/{id}/actions
     */
    function getActoins()
    {
        return $this->api('GET', [CURLOPT_URL => "https://api.trello.com/1/members/{$this->config['idModel']}/actions?key={$this->config['key']}&token={$this->config['token']}"]);
    }
    function renderActoins()
    {
        $r = $this->getActoins();
        $r = json_decode($r, true); ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-eOJMYsd53ii+scO/bJGFsiCZc+5NDVN2yr8+0RDqr0Ql0h+rP48ckxlpbzKgwra6" crossorigin="anonymous">
        <div class="container">
            <table class="table table-striped table-hover caption-top table-responsive">
                <caption class="text-center text-dark">Trello's feed</caption>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Board 🡆 Card</th>
                    <th>Action</th>
                </tr>
                <?php
                foreach ($r as $row) {
                    $user = $row['memberCreator'];
                    $action = '';
                    if ($row['type'] == 'updateCheckItemStateOnCard') $action = "<h6>Checklist: {$row['data']['checklist']['name']}</h6>" .
                        '<b> ' . ($row['data']['checkItem']['state'] == 'complete' ? 'Checked' : 'Unchecked') . '</b> ' . $row['data']['checkItem']['name'];
                    elseif ($row['type'] == 'updateChecklist') $action = "<h6>Checklist: {$row['data']['old']['name']}" . '</h6>' . ' <b>Renamed to</b> ' . $row['data']['checklist']['name'];
                    elseif ($row['type'] == 'addChecklistToCard') $action = "<b>Added checklist </b> " . $row['data']['checklist']['name'];
                    elseif ($row['type'] == 'addMemberToCard')  $action = "<b>Added to card</b> " . $row['member']['fullName'];
                    elseif ($row['type'] == 'removeMemberFromCard')  $action = "<b>Removed from card</b> " . $row['member']['fullName'];
                    elseif ($row['type'] == 'commentCard')  $action = "<b>Commented card</b><pre>{$row['data']['text']}</pre>";
                    elseif ($row['type'] == 'createCard')  $action = "<b>Created card</b>";
                    elseif ($row['type'] == 'updateCard') {
                        if (isset($row['data']['listBefore'], $row['data']['listAfter']))
                            $action = "<b>Moved from </b> " . $row['data']['listBefore']['name'] . "<b> to </b> " . $row['data']['listAfter']['name'];
                        elseif (isset($row['data']['old']['pos'])) $action = "<b>Changed position </b> in list " . $row['data']['list']['name'];
                    } ?>
                    <tr data-action="<?= $row['type'] ?>">
                        <td class="text-nowrap text-center w-auto">
                            <h5><?= date('H:i:s', strtotime($row['date'])) ?></h5>
                            <h6><?= date('Y-m-d', strtotime($row['date'])) ?></h6>
                        </td>
                        <td>
                            <a class="btn p-0" target="_blank" href="https://trello.com/<?= $user['username'] ?>">
                                <h6><?= $user['fullName'] ?></h6>
                                <img class="w-100" src="<?= $user['avatarUrl'] . '/50.png' ?>" alt="<?= $user['initials'] ?>">
                            </a>
                        </td>
                        <td class="w-50">
                            <a class="btn p-0 text-start" target="_blank" href="https://trello.com/c/<?= $row['data']['card']['shortLink'] ?>"><?= $row['data']['board']['name'] ?> 🡆 <?= $row['data']['card']['name'] ?></a>
                        </td>
                        <td class="w-100">
                            <?= $action ?>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </div>
<?php
    }
}
