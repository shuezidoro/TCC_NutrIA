<?php

function analisarRefeicaoComIA($descricaoComida) {
    $apiKey = "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"; 
    $url = "https://integrate.api.nvidia.com/v1/chat/completions";

    
    $promptSistema = "Você é um especialista em nutrição rigoroso integrado a um sistema.
    Sua função é analisar de forma realista a descrição enviada pelo usuário.
    
    REGRA CRUCIAL DE VALIDAÇÃO:
    Se o usuário digitar algo que NÃO SEJA um alimento, refeição, bebida ou algo comestível (por exemplo: objetos, ações aleatórias, sentimentos, palavras sem sentido como 'cadeira', 'corri 5km', 'estou triste'), você DEVE retornar EXATAMENTE o seguinte JSON:
    {\"erro\": \"nao_comivel\"}

    Caso seja um alimento válido, estime os nutrientes de forma realista.
    REGRAS DE PRECISÃO NUTRICIONAL:
    1. Se o usuário NÃO especificar o peso, assuma SEMPRE uma porção individual MÉDIA e PADRÃO de mercado. Nunca superestime os valores.
    2. Devolva OBRIGATORIAMENTE um objeto JSON plano contendo as seguintes chaves numéricas: 'kcal', 'carbo', 'proteina', 'gordura', 'acucar', 'fibra' e 'sodio'.
    3. O valor de 'sodio' deve ser estimado em miligramas (mg), enquanto os outros macros são em gramas (g).
    
    Regras de formatação:
    1. Não adicione saudações ou comentários.
    2. Não coloque blocos de formatação markdown (como ```json ou ```).
    3. Responda apenas com os dados puros em formato string JSON.";

    $promptUsuario = "Analise a seguinte refeição: " . $descricaoComida;

    $dados = [
        "model" => "meta/llama-3.3-70b-instruct",
        "messages" => [
            ["role" => "system", "content" => $promptSistema],
            ["role" => "user", "content" => $promptUsuario]
        ],
        "temperature" => 0.1,
        "top_p" => 0.7,
        "max_tokens" => 150,
        "stream" => false
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dados),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_SSL_VERIFYPEER => false 
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || !$response) {
        return false;
    }

    $resultadoAPI = json_decode($response, true);
    $textoResposta = $resultadoAPI['choices'][0]['message']['content'] ?? '';
    $textoRespostaClean = trim(str_replace(['```json', '```'], '', $textoResposta));

    return json_decode($textoRespostaClean, true);
}