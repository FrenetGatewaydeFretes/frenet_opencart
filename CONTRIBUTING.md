# Desenvolvimento do módulo Frenet para OpenCart 3

Guia para quem altera o módulo. As instruções para o lojista ficam no [README](README.md).

## Compatibilidade

Um único pacote atende todas as versões do OpenCart 3, de **3.0.0.0 a 3.0.5.1**, em **PHP 7.1 a 8.5**.

| OpenCart 3 | PHP aceito pelo OpenCart | Twig |
| --- | --- | --- |
| 3.0.0.0 a 3.0.3.4 | 5.4 em diante (o módulo exige 7.1) | 1.24 |
| 3.0.3.5 a 3.0.3.8 | 7.3 em diante | 2.13 |
| 3.0.3.9 a 3.0.4.1 | 7.3 em diante | 3.6 a 3.8 |
| 3.0.5.0 a 3.0.5.1 | 8.1 em diante | 3.21 a 3.24 |

### Regras de PHP

O código em `upload/` precisa rodar no PHP 7.1 e não pode gerar aviso no PHP 8.5.

Liberado no PHP 7.1: `declare(strict_types=1)`, tipos escalares em parâmetros, tipos de retorno, tipos anuláveis (`?int`), `void`, `??`, `[]`, visibilidade em constantes (`private const`).

Proibido, porque só existe em versões mais novas:

- PHP 7.2: tipo `object`, vírgula no fim de `use` agrupado.
- PHP 7.3: vírgula no fim da lista de argumentos de uma chamada, heredoc indentado.
- PHP 7.4: propriedades tipadas, arrow functions (`fn`), `??=`, spread em arrays.
- PHP 8.0 em diante: `match`, `mixed`, union types, nullsafe (`?->`), argumentos nomeados, promoção de propriedades no construtor, `readonly`, enums, atributos, e funções como `str_contains`, `str_starts_with` e `array_is_list`.

Proibido porque gera aviso no PHP 8.x:

- propriedades dinâmicas (declare toda propriedade na classe);
- passar `null` para funções de string do PHP (converta antes com `(string)`);
- `curl_close()`, que não faz nada desde o PHP 8.0 e está obsoleta no 8.5.

O `composer compat` (PHPCompatibility com `testVersion 7.1-`) e o `php -l` do CI, do PHP 7.1 ao 8.5, barram a maior parte disso.

### Regras de Twig

O template do admin precisa funcionar do Twig 1.24 ao 3.x. Use só `{{ }}`, `{% if %}`, `{% for %}` e o filtro `escape`. Não use a tag `spaceless`, a tag `filter`, `for ... if`, nem os filtros `filter` e `map`.

O OpenCart 3 roda o Twig com `autoescape` desligado, então todo valor impresso no template leva `|escape`.

## Estrutura

```
upload/                         vai para o .ocmod.zip; espelha a raiz da loja
  admin/...                     controller, idiomas e template do admin
  catalog/...                   model de frete e idiomas da loja
  system/library/frenet/        classes do módulo (namespace Frenet)
tests/
  unit/                         PHPUnit das classes de system/library/frenet
  mock/                         mock da API da Frenet (servidor embutido do PHP)
  stubs/                        Config e Log com a mesma API do core, para os testes
```

O autoloader do OpenCart 3 transforma o nome da classe no caminho do arquivo em minúsculas: `Frenet\Http\Client` fica em `system/library/frenet/http/client.php`. Por isso todo arquivo da biblioteca tem nome minúsculo.

As classes de `system/library/frenet/` recebem no construtor tudo o que usam (configuração, log, cliente HTTP). Controllers e models do OpenCart montam essas classes; as classes não buscam nada no registry.

## Padrão de código

O módulo segue o [padrão de código do OpenCart](https://github.com/opencart/opencart/wiki/Coding-standards): tabs, variáveis e arquivos em snake_case, classes e métodos em camelCase. O `.php-cs-fixer.php` é o da versão 3.0.5.1 do OpenCart, com três regras desligadas porque gerariam sintaxe acima do PHP 7.1 (`??=`, heredoc indentado e vírgula no fim de argumentos).

Os arquivos legados em `upload/admin` e `upload/catalog` entram no fixer à medida que forem reescritos.

## Comandos

As ferramentas rodam com o Composer (dependências só de desenvolvimento, fora do pacote). Sem PHP na máquina, use o Docker:

```bash
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app composer:2 composer check
```

| Comando | O que faz |
| --- | --- |
| `composer check` | roda `cs`, `compat`, `phpstan` e `test`, como o CI |
| `composer cs` / `composer cs-fix` | confere / aplica o padrão de código |
| `composer compat` | PHPCompatibility do PHP 7.1 em diante em `upload/` |
| `composer phpstan` | PHPStan nível 6 em `system/library/frenet`, analisando como PHP 7.1 |
| `composer test` | PHPUnit; sobe o mock da Frenet sozinho na porta 8765 |
| `composer lint` | `php -l` em `upload/` com o PHP atual |

Para conferir a sintaxe numa versão específica do PHP:

```bash
docker run --rm -v "$PWD":/app -w /app php:7.1-cli sh -c 'find upload -name "*.php" -print0 | xargs -0 -n1 php -l'
```

## Mock da API da Frenet

`tests/mock/router.php` imita a API REST da Frenet:

| Rota | Resposta |
| --- | --- |
| `POST /shipping/quote` sem header `token` | 401 |
| `POST /shipping/quote` com header `token` | 200 com PAC e SEDEX (`tests/mock/fixtures/quote.json`) |
| `/mock/status/{código}` | responde com esse status HTTP |
| `/mock/delay/{segundos}` | espera antes de responder, para testar tempo limite |
| `/mock/echo` | devolve método, headers e corpo recebidos |

Para usar fora dos testes:

```bash
php -S 127.0.0.1:8765 tests/mock/router.php
```

## Ambientes de teste

Os ambientes vêm do [instalador do OpenCart 3](https://github.com/Frenet-BR/opencart-v3-enviroment-installation). Cada demanda é testada nas versões que cobrem as faixas da tabela de compatibilidade:

| OpenCart | PHP |
| --- | --- |
| 3.0.0.0 | 7.1 |
| 3.0.3.4 | 7.4 |
| 3.0.3.8 | 7.4 |
| 3.0.5.1 | 8.2 |

```bash
OC_VERSION=3.0.0.0 OC_PHP_VERSION=7.1 SITE_URL=http://localhost:8300 ./install.sh
```

O pacote é instalado pelo admin, em **Extensões → Instalador**.

## Branches e commits

- `master`: só recebe versões publicadas.
- `3.0-develop`: integração; cada demanda entra aqui por PR.
- Uma branch por demanda, a partir da `3.0-develop`, com o número da issue do roadmap (ex.: `feature/889-base-tecnica`).
- Commits em inglês com gitmoji, uma responsabilidade por commit (ex.: `:sparkles: Add the cURL transport for the Frenet API`).
