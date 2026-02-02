Assistente Persistente
======================
Uma base simples para um chatbot com memória persistente em PHP puro, SQLite e JavaScript.

Visão geral
-----------
- Linguagem: PHP 7.4+
- Banco de dados: SQLite
- Frontend: HTML/CSS/JS
- Conector de IA: Ollama (LLMs locais) ou OpenAI (via API)

Recursos
--------
- Memória Persistente: histórico salvo em database.sqlite
- Contexto Configurável: envio das últimas mensagens para manter o contexto
- Interface simples: frontend direto
- Compatível com APIs OpenAI/OLLama/OpenRouter etc.

Pré-requisitos
--------------
- PHP 7.4 ou superior
- Extensões PHP: pdo_sqlite, curl
- Um backend de IA ativo (Ollama local ou chave OpenAI)

Instalação
----------
1. Clone ou baixe o repositório para seu ambiente
2. Edite config.php para apontar a API e o modelo desejado
3. Garanta permissões de escrita para o diretório (criando database.sqlite na primeira execução)

Configuração (exemplos)
-------------------------
Configuração básica para Ollama (local):
```php
return [
  'api_base_url' => 'http://localhost:11434/v1',
  'api_key'      => 'ollama',
  'model'        => 'llama3',
  'context_limit'=> 10
];
```
Configuração para OpenAI (exemplo):
```php
return [
  'api_base_url' => 'https://api.openai.com/v1',
  'api_key'      => 'sk-...'
  'model'        => 'gpt-4o-mini',
  'context_limit'=> 10
];
```

Execução
--------
1. No terminal, rode o servidor embutido do PHP (para teste):
```
php -S localhost:8000
```
2. Acesse: http://localhost:8000

Arquivos-chave
---------------
- index.php: frontend
- api.php: backend (DB + IA)
- config.php: configuração central
- database.sqlite: banco criado na primeira execução

Notas
-----
- O botão de limpar apenas afeta a visualização; o histórico permanece no banco para manter contexto.
- Este código é recomendado apenas para desenvolvimento/local; para produção, implemente autenticação/validações de segurança adequadas.

Observação: este README reflete o estado atual do repositório existente em /root/.openclaw/workspace/repos/Assistente-Persistente. Se desejar, posso estender com guias de contribuição, testes, ou scripts de setup automatizados.
