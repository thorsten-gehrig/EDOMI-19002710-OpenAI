###[DEF]###
[name		= OpenAI		]

[e#1		=Prompt 		]
[e#2		=API Key 		]
[e#3		=Model name 	]
[e#4		=Temperature 	]

[a#1		=Response		]

###[/DEF]###


###[HELP]###
LBS19002710 V0.2
Dieser Baustein Fragt die OpenAI API ab und gibt die Rückmeldung zurück.

E1: Der Prompt, der an die API gesendet wird.
E2: Dein OpenAI API-Schlüssel.
E3: Der Modellname (z.B. "gpt-3.5-turbo", "gpt-4o", ...).
E4: Die Temperatur (ein Float-Wert zwischen 0 und 1).

A1: Ergebnis als String
###[/HELP]###


###[LBS]###
<?
function LB_LBSID($id) {
	if ($E=getLogicEingangDataAll($id)) {
		if (getLogicElementStatus($id)==0) {                       //LBS läuft nicht?
			if ($E[1]['refresh']==1 &&  $E[1]['value']) {
				setLogicElementStatus($id,1);                             //LBS "starten"
				callLogicFunctionExec(LBSID,$id);                         //EXEC-Script starten
			}
		}
	}
}
?>

###[/LBS]###


###[EXEC]###
<?
require (dirname(__FILE__) . "/../../../../main/include/php/incl_lbsexec.php");

set_time_limit(5);
sql_connect();

// Eingänge abrufen
$E = logic_getInputs($id);

// Überprüfen, ob die notwendigen Eingänge existieren
if (isset($E[1]['value']) && isset($E[2]['value']) && isset($E[3]['value']) && isset($E[4]['value'])) {
    $prompt = $E[1]['value']; // Prompt
    $apiKey = $E[2]['value']; // API Key
    $model = $E[3]['value']; // Modellname, z.B. "gpt-3.5-turbo"
    $temperature = (float)$E[4]['value']; // Temperatur

    // Anfrage an die ChatGPT-API vorbereiten
    $url = "https://api.openai.com/v1/chat/completions";
    $data = array(
        "model" => $model,
        "messages" => array(array("role" => "user", "content" => $prompt)),
        "temperature" => $temperature
    );
    $payload = json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        logic_setOutput($id, 1, "CURL Error: " . $error);
    } else {
        $responseData = json_decode($response, true);
        if (isset($responseData['choices'][0]['message']['content'])) {
            $output = $responseData['choices'][0]['message']['content'];
            logic_setOutput($id, 1, $output);
        } else {
            logic_setOutput($id, 1, "Invalid API Response");
        }
    }
    curl_close($ch);
} else {
    logic_setOutput($id, 1, "Missing input values");
}
setLogicElementStatus($id,0);
sql_disconnect();	

?>
###[/EXEC]###

