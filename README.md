# Assistente Pessoal com Memória Persistente

Este é um projeto simples de um Chatbot com memória persistente, desenvolvido em PHP puro (sem frameworks), SQLite e JavaScript. Ele permite conversar com modelos de linguagem (LLMs) como Llama 3 (via Ollama) ou modelos da OpenAI, mantendo o histórico da conversa salvo em um banco de dados local.

## Funcionalidades

- **Memória Persistente**: O histórico do chat é salvo em um banco de dados SQLite (`database.sqlite`), permitindo que a conversa continue mesmo após recarregar a página.
- **Contexto Configurável**: O sistema envia as últimas `X` mensagens para a IA, garantindo que ela entenda o contexto recente sem estourar o limite de tokens.
- **Interface Simples**: Frontend limpo e responsivo em HTML/CSS/JS.
- **Agnóstico de API**: Compatível com APIs que seguem o padrão OpenAI (Ollama, OpenRouter, OpenAI, etc.).

## Pré-requisitos

- PHP 7.4 ou superior.
- Extensões PHP habilitadas:
  - `pdo_sqlite` (para o banco de dados)
  - `curl` (para requisições à API)
- Um servidor de API de LLM (ex: Ollama rodando localmente ou uma chave da OpenAI).

## Instalação e Configuração

1. **Clone ou baixe o repositório** para uma pasta local.

2. **Configure o ambiente:**
   Edite o arquivo `config.php` para definir a URL da API e o modelo desejado.

   Exemplo para **Ollama** (Local):
   ```php
   return [
       'api_base_url' => 'http://localhost:11434/v1',
       'api_key' => 'ollama', // Geralmente ignorado localmente
       'model' => 'llama3',   // Certifique-se de ter baixado o modelo: ollama pull llama3
       'context_limit' => 10
   ];
   ```

   Exemplo para **OpenAI**:
   ```php
   return [
       'api_base_url' => 'https://api.openai.com/v1',
       'api_key' => 'sk-sua-chave-aqui',
       'model' => 'gpt-4o-mini',
       'context_limit' => 10
   ];
   ```

3. **Permissões:**
   Certifique-se de que o diretório do projeto tem permissão de escrita, pois o script criará o arquivo `database.sqlite` automaticamente na primeira execução.

## Como Executar

Você pode usar o servidor embutido do PHP para testar rapidamente.

1. Abra o terminal na pasta do projeto.
2. Execute o comando:
   ```bash
   php -S localhost:8000
   ```
3. Acesse no navegador:
   http://localhost:8000

## Estrutura de Arquivos

- `index.php`: Interface do usuário (Frontend).
- `api.php`: Backend que gerencia o banco de dados e comunica com a IA.
- `config.php`: Arquivo de configuração central.
- `database.sqlite`: Banco de dados gerado automaticamente (contém a tabela `messages`).

## Notas

- **Limpeza do Chat**: O botão "Limpar" na interface atual limpa apenas a visualização no navegador. O histórico permanece no banco de dados para manter o contexto.
- **Segurança**: Este código é destinado a uso local ou de desenvolvimento. Para produção, recomenda-se adicionar autenticação e validações de segurança mais robustas.