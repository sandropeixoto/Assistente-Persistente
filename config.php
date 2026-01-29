<?php
// config.php
return [
    // Endpoint do OpenCode Zen. 
    // Nota: Para GPT-5 Nano, a documentação Zen aponta para 'https://opencode.ai/zen/v1/chat/completions'
    // ou '/responses' dependendo da versão do SDK. O padrão seguro é o abaixo.
    'api_url' => 'https://opencode.ai/zen/v1/chat/completions',
    
    // Sua chave de API (Obtenha em opencode.ai/auth após rodar /connect)
    'api_key' => 'sk-nhzogvPrZXkIvWRYQTiuSnaSHz3pl47c4mFl7fU7JAIvqGWI0a00045FqZeLPnFd',
    
    // O modelo gratuito e rápido solicitado
    'model' => 'gpt-5-nano',
    
    // Memória de curto prazo (últimas 10 mensagens)
    'context_limit' => 10,

    'debug_mode' => true
];