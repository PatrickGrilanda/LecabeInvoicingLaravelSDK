# Lecabe Invoicing — PHP SDK (Laravel)

Cliente HTTP em PHP para a API **LecabeInvoicing**: prefixo **`/v1`**, JSON em **snake_case**, erros no formato `{ "error": { "code", "message", "details?" } }`. Paridade **API 0.8.x**: núcleo de faturação (clientes, projetos, tempo, faturas, PDF, e-mail), **multi-auth** (API key, JWT, HTTP Basic, rotas públicas) e **punch timer** (`/v1/punch-timer/*`). Para explorar o contrato na tua instância, usa sobretudo **`{BASE_URL}/docs`** (Scalar); **`/documentation`** (Swagger UI) pode existir em paralelo quando a doc está activa.

## Onde vive este código

| | |
|--|--|
| **Repositório GitHub** | [github.com/PatrickGrilanda/LecabeInvoicingLaravelSDK](https://github.com/PatrickGrilanda/LecabeInvoicingLaravelSDK) |
| **Packagist** | *Não utilizado* — o pacote **não** está registado em packagist.org. |
| **Instalação na tua app Laravel** | Ainda assim usas o **Composer** no projeto Laravel, com `repositories` → `type: vcs` a apontar **para este repositório GitHub** (ver secção 3). |

Este README descreve **só** esse fluxo (GitHub como fonte única).

---

## Índice

1. [O que o pacote oferece](#1-o-que-o-pacote-oferece)
2. [Pré-requisitos](#2-pré-requisitos)
3. [Instalação via GitHub (Composer `type: vcs`)](#3-instalação-via-github-composer-type-vcs)
4. [Repositório privado no GitHub](#4-repositório-privado-no-github)
5. [Variáveis de ambiente no Laravel](#5-variáveis-de-ambiente-no-laravel)
6. [Registar o cliente no container (Service Provider)](#6-registar-o-cliente-no-container-service-provider)
7. [Uso em controllers, jobs e comandos](#7-uso-em-controllers-jobs-e-comandos)
8. [Matriz de compatibilidade API e SDK](#8-matriz-de-compatibilidade-api-e-sdk)
9. [Mapa dos recursos da API](#9-mapa-dos-recursos-da-api)
10. [Erros (`ApiException`)](#10-erros-apiexception)
11. [PDF e respostas HTTP no Laravel](#11-pdf-e-respostas-http-no-laravel)
12. [E-mail de fatura (`invoiceEmails`)](#12-e-mail-de-fatura-invoiceemails)
13. [Testes (PHPUnit) no teu projeto](#13-testes-phpunit-no-teu-projeto)
14. [Atualizar o SDK](#14-atualizar-o-sdk)
15. [Resolução de problemas](#15-resolução-de-problemas)
16. [Referência rápida de autenticação](#16-referência-rápida-de-autenticação)
17. [Punch timer (API 0.8.x)](#17-punch-timer-api-08x)

---

## 1. O que o pacote oferece

- **Pacote Composer:** `lecabe/invoicing-sdk`
- **Namespace:** `Lecabe\Invoicing\`
- **Classe principal:** `Lecabe\Invoicing\InvoicingClient` — recebe `Lecabe\Invoicing\Config` (base URL, API key, timeout)
- **Guzzle** como cliente HTTP (podes injetar `GuzzleHttp\ClientInterface` em testes)
- **Sem Service Provider oficial** no pacote: o registo no Laravel é **no teu** `AppServiceProvider` (ou módulo), como abaixo

Para **`sendV1`**, **`getV1`** e os recursos do cliente (clientes, projetos, faturas, punch timer, etc.), os pedidos **`/v1/*`** enviam automaticamente:

- cabeçalho **`X-API-Key`**
- cabeçalho **`Authorization: Bearer`** com **o mesmo valor** da API key

Para JWT, HTTP Basic ou rotas públicas de auth, usa os métodos dedicados em **`InvoicingClient`** (ver secção **16**); aí **não** se misturam credenciais de invoicing com o token JWT nem com Basic.

Os endpoints **`/health`** e **`/ready`** **não** enviam cabeçalhos de API key de invoicing.

---

## 2. Pré-requisitos

| Requisito | Notas |
|-----------|--------|
| **PHP** | ≥ 8.2 (igual ao `composer.json` do SDK) |
| **Laravel** | 10.x ou 11.x (ou superior compatível) |
| **API LecabeInvoicing** | Instância acessível por URL (ex.: `https://api.empresa.com` ou `http://127.0.0.1:3000` em desenvolvimento) |
| **API key** | Chave válida configurada no servidor da API (modo `v1-api-key` / documentação OpenAPI) |
| **Composer** | No projeto Laravel, para exigir o pacote via VCS |

Confirma na documentação interactiva da tua instância (**`GET {BASE_URL}/docs`**, Scalar) a versão do contrato (**0.8.x**). Se o servidor também expõe Swagger em **`/documentation`**, podes usá-lo como alternativa; o contrato é o mesmo quando a doc está activa (`ENABLE_API_DOCS`).

---

## 3. Instalação via GitHub (Composer `type: vcs`)

O Composer consegue instalar dependências directamente de um repositório **Git** onde exista um `composer.json` na **raiz** do repositório (não uses uma subpasta sem configurar `installer-paths` — o URL do VCS deve ser a raiz do pacote).

### 3.1. Adicionar o repositório ao `composer.json` da aplicação Laravel

Abre o **`composer.json`** do teu projeto Laravel (na raiz do Laravel, não o do SDK) e adiciona o bloco **`repositories`**. O campo **`url`** deve ser o clone HTTPS ou SSH do repositório GitHub onde está o SDK.

**Exemplo — este repositório (HTTPS):**

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/PatrickGrilanda/LecabeInvoicingLaravelSDK.git"
    }
  ],
  "require": {
    "php": "^8.2",
    "laravel/framework": "^11.0",
    "lecabe/invoicing-sdk": "dev-main"
  },
  "minimum-stability": "dev",
  "prefer-stable": true
}
```

Notas importantes:

- O **`name`** no `composer.json` do SDK é `lecabe/invoicing-sdk` — o `require` tem de usar **esse** identificador, não o nome do repo no GitHub.
- Se a branch principal se chama **`main`**, a versão que o Composer vê costuma ser **`dev-main`**. Se for **`master`**, usa **`dev-master`**.
- **`minimum-stability": "dev"`** (e idealmente **`prefer-stable": true`**) evita erros quando só existe branch de desenvolvimento sem tags estáveis.
- Se publicares **tags** semânticas no repo do SDK (ex.: `v1.0.0`), podes fixar com `"lecabe/invoicing-sdk": "^1.0"` e, em muitos casos, remover a necessidade de `minimum-stability: dev` para esse pacote.

### 3.2. Instalar

Na raiz do projeto Laravel:

```bash
composer update lecabe/invoicing-sdk
```

Ou, se ainda não está em `require`:

```bash
composer require lecabe/invoicing-sdk:dev-main
```

(ajusta `dev-main` à branch real.)

### 3.3. Verificar

```bash
composer show lecabe/invoicing-sdk
```

Deves ver a fonte como **source** Git e o **path** em `vendor/lecabe/invoicing-sdk`.

---

## 4. Repositório privado no GitHub

Se o SDK está num repo **privado**, o Composer precisa de **credenciais** para clonar.

### Opção A — HTTPS + token (recomendado em CI)

1. Cria um **Personal Access Token (classic)** no GitHub com scope **`repo`** (acesso ao código privado).
2. No servidor ou na tua máquina:

```bash
composer config --global github-oauth.github.com SEU_TOKEN_AQUI
```

Ou define a variável de ambiente **`COMPOSER_AUTH`** (JSON) com o token, útil em pipelines.

### Opção B — SSH

Usa o URL SSH no `repositories[].url`:

```json
"url": "git@github.com:PatrickGrilanda/LecabeInvoicingLaravelSDK.git"
```

(Se mantiveres um fork noutra organização, substitui `PatrickGrilanda` pelo owner do repositório.)

Garante que a máquina onde corres `composer install` tem chave SSH registada no GitHub (deploy key no repositório ou chave de utilizador).

### Opção C — `auth.json` local (não commits!)

```bash
composer config --auth github-oauth.github.com SEU_TOKEN
```

Mantém `auth.json` fora do Git (já costuma estar no `.gitignore` global do Composer).

---

## 5. Variáveis de ambiente no Laravel

No **`.env`** do projeto Laravel:

```env
LECABE_INVOICING_BASE_URL=https://api.exemplo.com
LECABE_INVOICING_API_KEY=cola_aqui_a_tua_chave
LECABE_INVOICING_TIMEOUT_SECONDS=30
```

- **`LECABE_INVOICING_BASE_URL`**: sem barra final ou com — o SDK normaliza; usa o **origin** da API (não incluas `/v1` aqui, o cliente acrescenta os paths).
- **`LECABE_INVOICING_API_KEY`**: obrigatória para chamadas autenticadas; em desenvolvimento podes deixar vazio só para `health`/`ready`, mas `/v1` falhará.

### Opcional — `config/services.php`

Para agrupar com outros serviços externos (Laravel 10/11):

```php
// config/services.php
'lecabe_invoicing' => [
    'base_url' => env('LECABE_INVOICING_BASE_URL', 'http://127.0.0.1:3000'),
    'api_key' => env('LECABE_INVOICING_API_KEY', ''),
    'timeout' => (float) env('LECABE_INVOICING_TIMEOUT_SECONDS', 30),
],
```

Depois usa `config('services.lecabe_invoicing.base_url')` no provider abaixo.

---

## 6. Registar o cliente no container (Service Provider)

O pacote **não** regista automaticamente o `InvoicingClient`. Faz o bind **singleton** para reutilizar a mesma instância (e a mesma conexão HTTP) por request.

**Ficheiro:** `app/Providers/AppServiceProvider.php`

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Lecabe\Invoicing\Config;
use Lecabe\Invoicing\InvoicingClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InvoicingClient::class, function () {
            return new InvoicingClient(new Config(
                baseUri: config('services.lecabe_invoicing.base_url', env('LECABE_INVOICING_BASE_URL', 'http://127.0.0.1:3000')),
                apiKey: config('services.lecabe_invoicing.api_key', env('LECABE_INVOICING_API_KEY', '')),
                timeout: (float) config('services.lecabe_invoicing.timeout', env('LECABE_INVOICING_TIMEOUT_SECONDS', 30)),
            ));
        });
    }

    public function boot(): void
    {
        //
    }
}
```

Se **não** usares `config/services.php`, podes simplificar só com `env()` (menos ideal em produção com config cache — antes de `php artisan config:cache`, garante que os valores vêm de `config/`).

---

## 7. Uso em controllers, jobs e comandos

### Injeção no construtor (recomendado)

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Lecabe\Invoicing\InvoicingClient;

class ClientController extends Controller
{
    public function index(Request $request, InvoicingClient $invoicing)
    {
        $page = (int) $request->get('page', 1);
        $result = $invoicing->clients()->list([
            'page' => $page,
            'per_page' => 20,
        ]);

        return view('clients.index', [
            'clients' => $result['data'] ?? [],
            'meta' => $result['meta'] ?? [],
        ]);
    }
}
```

### Jobs e comandos Artisan

```php
use Lecabe\Invoicing\InvoicingClient;

public function handle(InvoicingClient $invoicing): void
{
    $invoicing->ready();
}
```

### Resolução manual

```php
$invoicing = app(InvoicingClient::class);
```

---

## 8. Matriz de compatibilidade API e SDK

Tabela de **áreas HTTP** e **métodos do `InvoicingClient`** (alinhada às fases 20–23 e ao contrato **OpenAPI 0.8.x**). Para detalhes de auth, vê a [secção 16](#16-referência-rápida-de-autenticação).

| Área da API | Paths (resumo) | Entrada no `InvoicingClient` |
|-------------|----------------|------------------------------|
| **Sistema** | `GET /health`, `GET /ready` | `health()`, `ready()` |
| **Clientes** | `/v1/clients` | `clients()` |
| **Projetos** | `/v1/projects` | `projects()` |
| **Tempo** | `/v1/time-entries` | `timeEntries()` |
| **Faturas** | `/v1/invoices`, `/v1/invoices/from-time` | `invoices()` |
| **Linhas** | `/v1/invoices/:id/lines`, `…/recalculate` | `invoiceLines()` |
| **PDF** | `GET /v1/invoices/:id/pdf` | `invoicePdf()` |
| **E-mail** | `POST /v1/invoices/:id/emails` | `invoiceEmails()` |
| **Auth** | `/v1/auth/register`, `login`, `verify-email`, `resend-verification`, … | `auth()` (usa `sendV1Public` / `sendV1WithJwt` por baixo) |
| **Perfil (me)** | `GET /v1/me` | `me()` — **API key de invoicing** ligada a utilizador (não JWT de login) |
| **Chaves (utilizador)** | `POST /v1/users/me/api-keys` | `userMeApiKeys()` — **só JWT** |
| **Chaves (admin)** | `POST /v1/admin/api-keys` | `adminApiKeys()` — **HTTP Basic** (email + palavra-passe da conta) |
| **Punch timer** | `/v1/punch-timer/*` | `punchTimer()` — ver [secção 17](#17-punch-timer-api-08x) |

Pedidos **genéricos** a qualquer path `/v1` suportado pelo servidor: `sendV1`, `getV1`, `sendV1WithJwt`, `sendV1WithBasic`, `sendV1Public` (escolhe o transporte certo por rota).

### Fora do âmbito / pendente

- **Facades** existem para os recursos em `LaravelSDK/src/Resources/`; rotas novas no OpenAPI podem ser chamadas com os métodos `sendV1*` / `getV1` até haver um resource dedicado.
- **Fora de** `/v1` (além de `/health` e `/ready`) **não** há wrappers no SDK.

### Migração / modelo mental

- **`LECABE_INVOICING_API_KEY`** continua a ser o eixo para **dados de invoicing** (`clients`, `invoices`, `punchTimer`, etc.) e para **`GET /v1/me`** quando a chave está **associada a um utilizador** na API.
- **JWT** (sessão após `auth()->login`) serve para **`userMeApiKeys()`** e rotas que a API define como Bearer JWT — **não** substitui a API key em `me()` nem nos recursos de dados (ver [§16.2](#162-get-v1me-e-user_context_not_available)).
- **HTTP Basic** é um transporte **à parte** (ex.: `adminApiKeys()->create`) — credenciais da **conta**, não a API key de invoicing.
- **Rotas públicas** (`register`, `login`, `verify-email` sem auth de invoicing) usam **`sendV1Public`** ou `auth()` conforme o caso.

---

## 9. Mapa dos recursos da API

Todos os métodos abaixo estão em `InvoicingClient`.

| Método | Descrição resumida |
|--------|---------------------|
| `health()` | `GET /health` — sem API key |
| `ready()` | `GET /ready` — sem API key |
| `clients()` | CRUD + listagem paginada |
| `projects()` | CRUD + `list(['client_id' => ...])` |
| `timeEntries()` | CRUD + listagem com filtros (`project_id`, `client_id`, `from`, `to`, `billable`, `unbilled_only`, …) |
| `invoices()` | CRUD; `list()` só com `page`, `per_page`, `status`, `issue_from`, `issue_to`; `createFromTime([...])` → `POST /v1/invoices/from-time` |
| `invoiceLines()` | Linhas por fatura; `recalculate($invoiceId)` |
| `invoicePdf()` | `download($id)` → bytes PDF (binário) |
| `invoiceEmails()` | `send($id, $payload)` → JSON conforme API |
| `punchTimer()` | Cronómetro (`/v1/punch-timer/*`) — **API 0.8.x**; ver [secção 17](#17-punch-timer-api-08x) |
| `userMeApiKeys()` | `POST /v1/users/me/api-keys` — só JWT (login); pré-condições e erros em [§16.1.1](#1611-criação-de-chaves-api) |
| `adminApiKeys()` | `POST /v1/admin/api-keys` — HTTP Basic (conta); pré-condições e erros em [§16.1.1](#1611-criação-de-chaves-api) |

Respostas JSON são **arrays PHP** associativos (`snake_case` como na API). O PDF **não** é JSON — é `string` binária.

---

## 10. Erros (`ApiException`)

Em erros **4xx/5xx** com corpo JSON no formato da API, o SDK lança **`Lecabe\Invoicing\Exception\ApiException`**:

- `getMessage()` — mensagem humana
- `$e->httpStatus` — código HTTP
- `$e->errorCode` — código da API (ex.: `EMAIL_NOT_CONFIGURED`, `UNAUTHORIZED`, `EMAIL_NOT_VERIFIED`, `USER_CONTEXT_NOT_AVAILABLE`)
- `$e->details` — opcional (ex.: validação por campo)
- `$e->rawBody` — corpo bruto da resposta (útil para logs)

Exemplo em controller:

```php
use Lecabe\Invoicing\Exception\ApiException;
use Illuminate\Support\Facades\Log;

try {
    $invoicing->invoiceEmails()->send($id, ['to' => 'a@b.com']);
} catch (ApiException $e) {
    Log::warning('Lecabe API', [
        'status' => $e->httpStatus,
        'code' => $e->errorCode,
        'body' => $e->rawBody,
    ]);
    return back()->withErrors(['api' => $e->getMessage()]);
}
```

---

## 11. PDF e respostas HTTP no Laravel

O método `invoicePdf()->download($uuid)` devolve **`string`** (bytes). Para enviar ao browser:

```php
use Lecabe\Invoicing\Exception\ApiException;
use Lecabe\Invoicing\InvoicingClient;

public function pdf(InvoicingClient $invoicing, string $invoiceId)
{
    try {
        $bytes = $invoicing->invoicePdf()->download($invoiceId);
    } catch (ApiException $e) {
        abort($e->httpStatus, $e->getMessage());
    }

    return response($bytes, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="fatura.pdf"',
    ]);
}
```

**Não** faças `json_encode` nem `response()->json()` sobre o PDF.

---

## 12. E-mail de fatura (`invoiceEmails`)

Corpo permitido pela API (o SDK filtra chaves estranhas, mas deve respeitar o contrato):

- **`to`** (obrigatório) — e-mail
- **`subject`** (opcional)
- **`attach_pdf`** (opcional, boolean)
- **`fiscal_attachment`** (opcional) — `filename` + `content_base64`

Confirma os campos exactos em **`{BASE_URL}/docs`** para a tua versão **0.8.x**.

```php
$invoicing->invoiceEmails()->send($invoiceId, [
    'to' => 'cliente@empresa.com',
    'subject' => 'A sua fatura',
    'attach_pdf' => true,
]);
```

Se o servidor de e-mail não estiver configurado, a API pode responder **503** com código **`EMAIL_NOT_CONFIGURED`**.

---

## 13. Testes (PHPUnit) no teu projeto

- Em testes unitários, podes **mockar** `InvoicingClient` com Laravel (`Mockery`) ou injectar um `GuzzleHttp\Client` com **`MockHandler`** (o construtor de `InvoicingClient` aceita `ClientInterface`).
- Não é obrigatório ter a API real no CI — o pacote do SDK também testa com HTTP mockado.

---

## 14. Atualizar o SDK

Quando fizeres push de alterações ao repositório GitHub do SDK:

```bash
composer update lecabe/invoicing-sdk
```

Se usares **`dev-main`**, o Composer pode cachear; força refresh:

```bash
composer clear-cache
composer update lecabe/invoicing-sdk -W
```

Com **tags** semânticas, fixa versões no `composer.json` da app para upgrades controlados.

---

## 15. Resolução de problemas

| Sintoma | Possível causa |
|---------|----------------|
| `Could not find package lecabe/invoicing-sdk` | `repositories` mal colocado ou URL errado; o `composer.json` do SDK tem de estar na **raiz** do Git. |
| `dev-main` não encontrado | Branch no GitHub não se chama `main`; usa `dev-<nome-da-branch>`. |
| `403` ao instalar repo privado | Falta `github-oauth` ou SSH. |
| `401` / `UNAUTHORIZED` na API | `LECABE_INVOICING_API_KEY` vazia ou errada; confirma o mesmo valor nos headers esperados pela API. |
| `cURL error 60` SSL | Certificado em desenvolvimento; em produção corrige CA; em local **não** desactivar verificação SSL em código de produção. |
| PDF corrompido | Estás a tratar o PDF como JSON ou a passar por `json_encode`; usa resposta binária como na secção 11. |
| Timeout | Aumenta `timeout` em `Config` ou `LECABE_INVOICING_TIMEOUT_SECONDS`. |

---

## 16. Referência rápida de autenticação

### 16.1 Modos de transporte em `/v1`

A API usa vários estilos de autenticação; o SDK expõe **caminhos separados** para não enviar `X-API-Key` nem `Bearer` da API key de invoicing por engano.

| Modo | Quando usar | Método no `InvoicingClient` | Cabeçalhos enviados pelo SDK |
|------|----------------|-----------------------------|------------------------------|
| **API key (invoicing)** | Recursos de dados e **`GET /v1/me`** (perfil do utilizador vinculado à chave) | `sendV1`, `getV1`, ou facades (`clients()`, `me()`, `invoices()`, …) | `X-API-Key` + `Authorization: Bearer` com **a mesma** chave (`INVOICING_API_KEY` / `Config::apiKey`) |
| **JWT** | Sessão de utilizador (`resend-verification`, criação de chave em `userMeApiKeys()->create`) | `sendV1WithJwt($method, $path, $jwt, $options?)` | Só `Authorization: Bearer <jwt>`. **Não** usa `INVOICING_API_KEY` como token. |
| **HTTP Basic** | Rotas de conta/admin com email+palavra-passe (ex.: `POST /v1/admin/api-keys` via `adminApiKeys()->create`) | `sendV1WithBasic($method, $path, $user, $password, $options?)` | Só `Authorization: Basic <base64>` |
| **Público** | Registo, login, verify-email sem credenciais de invoicing | `sendV1Public($method, $path, $options?)` | Nenhum cabeçalho de auth por defeito; podes passar cabeçalhos em `$options['headers']` se precisares |

O SDK expõe **`$client->auth()`** (registo, login, verify-email públicos; `resend-verification` com JWT), **`$client->me()`** para `GET /v1/me` com API key de invoicing, e os recursos de criação de chaves em [§16.1.1](#1611-criação-de-chaves-api).

#### Exemplos mínimos (copiar e colar)

**API key (predefinida)** — mesma chave que `LECABE_INVOICING_API_KEY`:

```php
$invoicing->clients()->list(['page' => 1, 'per_page' => 20]);
$profile = $invoicing->me()->get();
```

**JWT** — sem `X-API-Key`; o Bearer é **só** o access token de login:

```php
$jwt = $data['access_token']; // ex.: resposta de $invoicing->auth()->login([...])
$invoicing->sendV1WithJwt('POST', '/v1/auth/resend-verification', $jwt, ['json' => new \stdClass()]);
$invoicing->userMeApiKeys()->create($jwt, ['label' => 'Minha app']);
```

**HTTP Basic** — credenciais da conta (não a API key de invoicing):

```php
$invoicing->adminApiKeys()->create('admin@empresa.com', 'segredo-forte', ['label' => 'Deploy']);
// equivalente de transporte:
// $invoicing->sendV1WithBasic('POST', '/v1/admin/api-keys', 'admin@empresa.com', 'segredo-forte', ['json' => ['label' => 'Deploy']]);
```

**Público** — registo / login / verify-email sem auth de invoicing:

```php
$invoicing->sendV1Public('POST', '/v1/auth/register', [
    'json' => ['email' => 'novo@empresa.com', 'password' => '...'],
]);
```

#### 16.1.1 Criação de chaves API

Corpo opcional em ambos: `label` (string), `expires_at` (ISO 8601 ou `null` para sem expiração). Resposta **201** inclui `id`, `api_key`, `label`, `expires_at`.

- **`$client->userMeApiKeys()->create($jwt, [...])`** — `$jwt` é o **access token** de `$client->auth()->login(...)`. Transporte **só JWT** (sem `X-API-Key`). **Pré-condição:** e-mail da conta verificado (`GET /v1/auth/verify-email?token=...`). Códigos típicos em `error.code`: **`EMAIL_NOT_VERIFIED` (403)**, `UNAUTHORIZED` (401), `BAD_REQUEST` (400); em ambiente sem migrações pode surgir `DATABASE_NOT_READY` (503), como noutros `/v1`.

- **`$client->adminApiKeys()->create($email, $password, [...])`** — credenciais da **conta** em HTTP Basic (sem JWT neste endpoint). **Pré-condição:** e-mail dessa conta verificado. Códigos típicos: `UNAUTHORIZED` (401), **`EMAIL_NOT_VERIFIED` (403)**, `BAD_REQUEST` (400), `DATABASE_NOT_READY` (503).

Tratamento compacto de **`EMAIL_NOT_VERIFIED`** (e outros códigos) com **`ApiException`**:

```php
use Lecabe\Invoicing\Exception\ApiException;

try {
    $invoicing->userMeApiKeys()->create($jwt, ['label' => 'CI']);
} catch (ApiException $e) {
    if ($e->httpStatus === 403 && $e->errorCode === 'EMAIL_NOT_VERIFIED') {
        // Conta ainda não verificada — orientar utilizador a abrir o link de verificação
    }
    throw $e;
}
```

Em ambos os fluxos de criação de chaves, usa **`$e->errorCode`** e **`$e->httpStatus`** conforme a API — **não** reinterpretes o erro no cliente (os testes do SDK garantem a propagação de cabeçalhos e de códigos como `EMAIL_NOT_VERIFIED`).

### 16.2 GET /v1/me e USER_CONTEXT_NOT_AVAILABLE

Chama **`$client->me()->get()`** com o mesmo transporte de **API key de invoicing** que os outros recursos (`X-API-Key` + `Authorization: Bearer` com a mesma chave).

- Se a chave for a **global** `INVOICING_API_KEY` do ambiente do servidor, ou uma chave **sem utilizador associado**, a API responde **403** com `error.code` **`USER_CONTEXT_NOT_AVAILABLE`**. O SDK propaga isto como **`ApiException`** — usa `$e->errorCode === 'USER_CONTEXT_NOT_AVAILABLE'` para distinguir de outros 403 (ex.: `EMAIL_NOT_VERIFIED` nas rotas de chaves).

```php
try {
    $invoicing->me()->get();
} catch (ApiException $e) {
    if ($e->httpStatus === 403 && $e->errorCode === 'USER_CONTEXT_NOT_AVAILABLE') {
        // Chave sem contexto de utilizador — usar outra chave ou fluxo admin/JWT conforme a tua app
    }
    throw $e;
}
```

- O **JWT** obtido com `$client->auth()->login(...)` **não** serve para `/v1/me` (o servidor espera API key de invoicing neste endpoint). Ver também a [matriz](#8-matriz-de-compatibilidade-api-e-sdk) e [§16.1](#161-modos-de-transporte-em-v1).

### 16.3 Resumo

- **Recursos existentes** (facades): **`X-API-Key: <key>`** e **`Authorization: Bearer <key>`** (o mesmo `<key>` da configuração).
- **Criação de chaves:** `userMeApiKeys()` (JWT) e `adminApiKeys()` (Basic) — sem `X-API-Key` nesses pedidos; erros via `ApiException` como em [§16.1.1](#1611-criação-de-chaves-api).
- **`/health`** e **`/ready`**: sem estes cabeçalhos.

---

## 17. Punch timer (API 0.8.x)

O cronómetro por projeto usa os mesmos cabeçalhos de API key que o resto de `/v1`, e **em todos os pedidos** o cabeçalho obrigatório **`X-Punch-Actor-Id`** (identificador opaco do utilizador). Opcionalmente **`X-Civil-Timezone`** (IANA).

| Método | Endpoint | Notas |
|--------|----------|--------|
| `status($actorId, $projectId, $civilTimezone?)` | `GET /v1/punch-timer/status` | query `project_id` |
| `play($actorId, $projectId, $civilTimezone?)` | `POST /v1/punch-timer/play` | body `{ "project_id" }` |
| `pause($actorId, $projectId, $civilTimezone?)` | `POST /v1/punch-timer/pause` | query `project_id` |
| `resume($actorId, $projectId, $civilTimezone?)` | `POST /v1/punch-timer/resume` | query `project_id` |
| `days($actorId, ['from' => 'Y-m-d', 'to' => 'Y-m-d', 'project_id' => ?], $civilTimezone?)` | `GET /v1/punch-timer/days` | omitir `project_id` para agregar por actor no intervalo |

### Validação / erros

- **`X-Punch-Actor-Id`:** obrigatório em todos os métodos (parâmetro `$actorId`); o SDK envia o cabeçalho em todos os pedidos. Validação de actor vazio ou timezone inválida é feita pela API — respostas **422** com `error.code` `VALIDATION_ERROR` (e mensagens alinhadas ao servidor) surgem como **`ApiException`**, tal como na [secção 10](#10-erros-apiexception).
- **`X-Civil-Timezone`:** opcional (`$civilTimezone`); o cabeçalho só é enviado quando passas uma string não vazia. Omitir ou deixar vazio corresponde no servidor a civil **`UTC`** (ver contrato HTTP).
- **409** `CONFLICT` (ex.: pausar sem `running`) também mapeia para **`ApiException`**.

Contrato HTTP completo (cabeçalhos, query, corpo, tabela de erros):

- No **repositório da API** (LecabeInvoicing): [documento `PUNCH-TIMER.md` no GitHub](https://github.com/PatrickGrilanda/LecabeInvoicing/blob/main/docs/PUNCH-TIMER.md) — útil se só clonaste o SDK.
- Na **tua instância**: abre **`{BASE_URL}/docs`** e confirma os paths `/v1/punch-timer/*` no OpenAPI publicado.

Os testes de forma de pedido e cabeçalhos estão em `tests/Unit/PunchTimerResourceTest.php`.

Exemplo mínimo:

```php
$actor = 'user-hr-id-opaque';
$projectId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

$invoicing->punchTimer()->play($actor, $projectId);
$invoicing->punchTimer()->pause($actor, $projectId);

$week = $invoicing->punchTimer()->days($actor, [
    'from' => '2026-04-01',
    'to' => '2026-04-06',
]);
```

---

## Desenvolvimento do próprio pacote (SDK)

```bash
git clone https://github.com/PatrickGrilanda/LecabeInvoicingLaravelSDK.git
cd LecabeInvoicingLaravelSDK
composer install
composer test
```

Com SSH:

```bash
git clone git@github.com:PatrickGrilanda/LecabeInvoicingLaravelSDK.git
```

Variáveis opcionais para integração real: ver `tests/Integration/README.md`.
