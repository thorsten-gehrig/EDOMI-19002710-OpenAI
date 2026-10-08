###[DEF]###
[name		= OpenAI		]

[e#1		=Prompt 		]
[e#2		=API Key 		]
[e#3		=Model name 	]
[e#4		=Reasoning effort 	]

[a#1		=Response		]

###[/DEF]###


###[HELP]###
LBS19002710 V0.3
Dieser Baustein fragt die OpenAI Responses API ab und gibt die Rückmeldung zurück.

E1: Der Prompt, der an die API gesendet wird.
E2: Dein OpenAI API-Schlüssel.
E3: Der Modellname (z.B. "gpt-6-luna", "gpt-6.1-sol", "gpt-6-astra", ...).
E4: Reasoning effort (optional: none, minimal, low, medium, high, xhigh, max).
    Leer lassen für den Modell-Standard.

A1: Ergebnis als String

Hinweis: Seit V0.3 wird die Responses API (/v1/responses) verwendet.
Aktuelle Modelle sind Reasoning-Modelle; die frühere "Temperatur" wird
nicht mehr unterstützt und wurde durch "Reasoning effort" ersetzt.
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

set_time_limit(120);
sql_connect();

// Eingänge abrufen
$E = logic_getInputs($id);

// Überprüfen, ob die notwendigen Eingänge existieren
if (isset($E[1]['value']) && isset($E[2]['value']) && isset($E[3]['value'])) {
    $prompt = $E[1]['value'];                       // Prompt
    $apiKey = $E[2]['value'];                       // API Key
    $model = trim($E[3]['value']);                  // Modellname, z.B. "gpt-6-luna"
    $reasoningEffort = isset($E[4]['value']) ? trim($E[4]['value']) : ""; // Reasoning effort (optional)

    // Anfrage an die Responses API vorbereiten
    $url = "https://api.openai.com/v1/responses";
    $data = array(
        "model" => $model,
        "input" => $prompt
    );
    if ($reasoningEffort !== "") {
        $data["reasoning"] = array("effort" => $reasoningEffort);
    }
    $payload = json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);   // Verbindungsaufbau
    curl_setopt($ch, CURLOPT_TIMEOUT, 110);         // Gesamtdauer (Reasoning kann dauern)
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseData = json_decode($response, true);

        if (isset($responseData['error'])) {
            // Fehlerobjekt der API (z.B. 401, 429, 400)
            $msg = isset($responseData['error']['message']) ? $responseData['error']['message'] : "unknown error";
            logic_setOutput($id, 1, "API Error (" . $httpCode . "): " . $msg);
        } else {
            // Text aus dem output-Array zusammensetzen (typ == "output_text")
            $text = "";
            if (isset($responseData['output']) && is_array($responseData['output'])) {
                foreach ($responseData['output'] as $item) {
                    if (isset($item['type']) && $item['type'] == "message" && isset($item['content']) && is_array($item['content'])) {
                        foreach ($item['content'] as $part) {
                            if (isset($part['type']) && $part['type'] == "output_text") {
                                $text .= $part['text'];
                            } elseif (isset($part['type']) && $part['type'] == "refusal") {
                                $text .= $part['refusal'];
                            }
                        }
                    }
                }
            }

            if ($text !== "") {
                logic_setOutput($id, 1, $text);
            } elseif (isset($responseData['status']) && $responseData['status'] == "incomplete") {
                logic_setOutput($id, 1, "Response incomplete (max_output_tokens)");
            } else {
                logic_setOutput($id, 1, "Invalid API Response (" . $httpCode . ")");
            }
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
