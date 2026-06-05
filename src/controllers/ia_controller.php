<?php

function analisarRefeicaoComIA($descricaoComida) {
    $apiKey = "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"; 
    $url = "https://integrate.api.nvidia.com/v1/chat/completions";

    // Adicionámos açúcar e fibra no pedido do JSON
    $promptSistema = "Você é um especialista em nutrição rigoroso integrado a um sistema.
    Sua função é analisar de forma realista a descrição enviada pelo usuário.
    
    REGRA CRUCIAL DE VALIDAÇÃO:
    Se o usuário digitar algo que NÃO SEJA um alimento, refeição, bebida ou algo comestível (por exemplo: objetos, ações aleatórias, sentimentos, palavras sem sentido como 'cadeira', 'corri 5km', 'estou triste'), você DEVE retornar EXATAMENTE o seguinte JSON:
    {\"erro\": \"nao_comivel\"}

    Caso seja um alimento válido, estime os nutrientes de forma realista.
    REGRAS DE PRECISÃO NUTRICIONAL:
    1. Se o usuário NÃO especificar o peso (ex: 'um pão de queijo', 'uma fatia de mamão'), assuma SEMPRE uma porção individual MÉDIA e PADRÃO de mercado (ex: pão de queijo de 40g-50g, fatia de mamão de 100g). Nunca superestime os valores.
    2. Seja extremamente atento às proteínas de alimentos que são majoritariamente carboidratos ou frutas. Frutas, café e açúcar puro têm quase ZERO proteína. Pão de queijo médio tradicional tem entre 2g e 3.5g de proteína. Portanto, o cálculo final deve ser a soma matemática lógica desses alimentos.

    Você DEVE responder estritamente com um objeto JSON válido, contendo exatamente as chaves: 'kcal', 'carbo', 'proteina', 'gordura', 'acucar' e 'fibra'.
    
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
        "max_tokens" => 1024,
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