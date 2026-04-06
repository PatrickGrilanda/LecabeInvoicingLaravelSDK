# Lecabe Invoicing — PHP SDK (Laravel)

Cliente HTTP em PHP para a API **LecabeInvoicing**: prefixo **`/v1`**, JSON em **snake_case**, erros no formato `{ "error": { "code", "message", "details?" } }`. Paridade **0.6.x** (faturas, tempo, PDF, e-mail) e **0.7.1+** para **punch timer** (`/v1/punch-timer/*`).

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
8. [Mapa dos recursos da API](#8-mapa-dos-recursos-da-api)
9. [Erros (`ApiException`)](#9-erros-apiexception)
10. [PDF e respostas HTTP no Laravel](#10-pdf-e-respostas-http-no-laravel)
11. [E-mail de fatura (`invoiceEmails`)](#11-e-mail-de-fatura-invoiceemails)
12. [Testes (PHPUnit) no teu projeto](#12-testes-phpunit-no-teu-projeto)
13. [Atualizar o SDK](#13-atualizar-o-sdk)
14. [Resolução de problemas](#14-resolução-de-problemas)
15. [Referência rápida de autenticação](#15-referência-rápida-de-autenticação)
16. [Punch timer (API 0.7.1+)](#16-punch-timer-api-071)

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

Para JWT, HTTP Basic ou rotas públicas de auth, usa os métodos dedicados em **`InvoicingClient`** (ver secção **15**); aí **não** se misturam credenciais de invoicing com o token JWT nem com Basic.

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

Confirma na documentação OpenAPI da tua instância (ex.: `GET /documentation`) a versão alinhada (**0.6.x** para o núcleo; **0.7.1+** se usares punch timer).

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

## 8. Mapa dos recursos da API

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
| `punchTimer()` | Cronómetro (`/v1/punch-timer/*`) — **API 0.7.1+**; ver [secção 16](#16-punch-timer-api-071) |

Respostas JSON são **arrays PHP** associativos (`snake_case` como na API). O PDF **não** é JSON — é `string` binária.

---

## 9. Erros (`ApiException`)

Em erros **4xx/5xx** com corpo JSON no formato da API, o SDK lança **`Lecabe\Invoicing\Exception\ApiException`**:

- `getMessage()` — mensagem humana
- `$e->httpStatus` — código HTTP
- `$e->errorCode` — código da API (ex.: `EMAIL_NOT_CONFIGURED`, `UNAUTHORIZED`)
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

## 10. PDF e respostas HTTP no Laravel

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

## 11. E-mail de fatura (`invoiceEmails`)

Corpo permitido pela API (o SDK filtra chaves estranhas, mas deve respeitar o contrato):

- **`to`** (obrigatório) — e-mail
- **`subject`** (opcional)
- **`attach_pdf`** (opcional, boolean)
- **`fiscal_attachment`** (opcional) — `filename` + `content_base64`

**Não** existem campos `html`, `text` ou `body` neste endpoint na API 0.6.0.

```php
$invoicing->invoiceEmails()->send($invoiceId, [
    'to' => 'cliente@empresa.com',
    'subject' => 'A sua fatura',
    'attach_pdf' => true,
]);
```

Se o servidor de e-mail não estiver configurado, a API pode responder **503** com código **`EMAIL_NOT_CONFIGURED`**.

---

## 12. Testes (PHPUnit) no teu projeto

- Em testes unitários, podes **mockar** `InvoicingClient` com Laravel (`Mockery`) ou injectar um `GuzzleHttp\Client` com **`MockHandler`** (o construtor de `InvoicingClient` aceita `ClientInterface`).
- Não é obrigatório ter a API real no CI — o pacote do SDK também testa com HTTP mockado.

---

## 13. Atualizar o SDK

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

## 14. Resolução de problemas

| Sintoma | Possível causa |
|---------|----------------|
| `Could not find package lecabe/invoicing-sdk` | `repositories` mal colocado ou URL errado; o `composer.json` do SDK tem de estar na **raiz** do Git. |
| `dev-main` não encontrado | Branch no GitHub não se chama `main`; usa `dev-<nome-da-branch>`. |
| `403` ao instalar repo privado | Falta `github-oauth` ou SSH. |
| `401` / `UNAUTHORIZED` na API | `LECABE_INVOICING_API_KEY` vazia ou errada; confirma o mesmo valor nos headers esperados pela API. |
| `cURL error 60` SSL | Certificado em desenvolvimento; em produção corrige CA; em local **não** desactivar verificação SSL em código de produção. |
| PDF corrompido | Estás a tratar o PDF como JSON ou a passar por `json_encode`; usa resposta binária como na secção 10. |
| Timeout | Aumenta `timeout` em `Config` ou `LECABE_INVOICING_TIMEOUT_SECONDS`. |

---

## 15. Referência rápida de autenticação

### 15.1 Modos de transporte em `/v1`

A API usa vários estilos de autenticação; o SDK expõe **caminhos separados** para não enviar `X-API-Key` nem `Bearer` da API key de invoicing por engano.

| Modo | Quando usar | Método no `InvoicingClient` | Cabeçalhos enviados pelo SDK |
|------|----------------|-----------------------------|------------------------------|
| **API key (invoicing)** | Recursos de dados (clientes, projetos, tempo, faturas, PDF, e-mail, punch timer, …) | `sendV1`, `getV1`, ou facades (`clients()`, `invoices()`, …) | `X-API-Key` + `Authorization: Bearer` com **a mesma** chave (`INVOICING_API_KEY` / `Config::apiKey`) |
| **JWT** | Sessão de utilizador (`/v1/me`, `resend-verification`, chaves por utilizador em fases futuras) | `sendV1WithJwt($method, $path, $jwt, $options?)` | Só `Authorization: Bearer <jwt>`. **Não** usa `INVOICING_API_KEY` como token. |
| **HTTP Basic** | Rotas de conta/admin com email+palavra-passe (ex.: `POST /v1/admin/api-keys`) | `sendV1WithBasic($method, $path, $user, $password, $options?)` | Só `Authorization: Basic <base64>` |
| **Público** | Registo, login, verify-email sem credenciais de invoicing | `sendV1Public($method, $path, $options?)` | Nenhum cabeçalho de auth por defeito; podes passar cabeçalhos em `$options['headers']` se precisares |

As **fases 21–22** do roadmap do SDK acrescentarão *wrappers* de recurso para auth, `me` e criação de chaves; **esta versão** acrescenta apenas estes métodos de transporte — deves indicar `path` e `json`/`query` como nos exemplos Guzzle habituais.

### 15.2 Resumo

- **Recursos existentes** (facades): **`X-API-Key: <key>`** e **`Authorization: Bearer <key>`** (o mesmo `<key>` da configuração).
- **`/health`** e **`/ready`**: sem estes cabeçalhos.

---

## 16. Punch timer (API 0.7.1+)

O cronómetro por projeto usa os mesmos cabeçalhos de API key que o resto de `/v1`, e **em todos os pedidos** o cabeçalho obrigatório **`X-Punch-Actor-Id`** (identificador opaco do utilizador). Opcionalmente **`X-Civil-Timezone`** (IANA).

| Método | Endpoint | Notas |
|--------|----------|--------|
| `status($actorId, $projectId, $civilTimezone?)` | `GET /v1/punch-timer/status` | query `project_id` |
| `play($actorId, $projectId, $civilTimezone?)` | `POST /v1/punch-timer/play` | body `{ "project_id" }` |
| `pause($actorId, $projectId, $civilTimezone?)` | `POST /v1/punch-timer/pause` | query `project_id` |
| `resume($actorId, $projectId, $civilTimezone?)` | `POST /v1/punch-timer/resume` | query `project_id` |
| `days($actorId, ['from' => 'Y-m-d', 'to' => 'Y-m-d', 'project_id' => ?], $civilTimezone?)` | `GET /v1/punch-timer/days` | omitir `project_id` para agregar por actor no intervalo |

Erros **409** (ex.: pausar sem `running`) e **422** (validação) mapeiam para **`ApiException`** como nos outros recursos.

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
