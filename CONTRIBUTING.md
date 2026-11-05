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

Os templates do admin e do simulador precisam funcionar do Twig 1.24 ao 3.x. Use só `{{ }}`, `{% if %}`, `{% for %}` e o filtro `escape`. Não use a tag `spaceless`, a tag `filter`, `for ... if`, nem os filtros `filter` e `map`.

O OpenCart 3 roda o Twig com `autoescape` desligado, então todo valor impresso no template leva `|escape`.

Em versões antigas do OpenCart 3, como a 3.0.3.3, o catálogo e o admin guardam o template compilado e não conferem se o arquivo mudou (o Twig roda sem `auto_reload`). Por isso o admin e o simulador renderizam os templates da Frenet com `template_cache` desligado. Sem isso, depois de uma atualização do módulo, a loja mostraria o template antigo até o lojista limpar o cache de tema.

O JavaScript do simulador roda com o jQuery do tema padrão, do 2.1.1 (3.0.0.0) ao 3.7.1 (3.0.5.1), e o Bootstrap 3. Ele não usa recursos que só existem em versões mais novas, e textos vindos do servidor entram na página por `.text()`.

## Estrutura

```
upload/                         vai para o .ocmod.zip; espelha a raiz da loja
  admin/...                     controller, idiomas e template do admin
  catalog/...                   model de frete, controller e template do simulador, idiomas da loja
  system/library/frenet/        classes do módulo (namespace Frenet)
    quote/                      montagem da requisição e leitura da resposta da cotação
    simulator/                  produto da página e limite por IP do simulador
tests/
  unit/                         PHPUnit das classes de system/library/frenet
  mock/                         mock da API da Frenet (servidor embutido do PHP)
  stubs/                        Config, Log, DB e Cache com a mesma API do core, para os testes
```

O autoloader do OpenCart 3 transforma o nome da classe no caminho do arquivo em minúsculas: `Frenet\Http\Client` fica em `system/library/frenet/http/client.php`. Por isso todo arquivo da biblioteca tem nome minúsculo.

As classes de `system/library/frenet/` recebem no construtor tudo o que usam (configuração, log, cliente HTTP). Controllers e models do OpenCart montam essas classes; as classes não buscam nada no registry.

A cotação passa por estas classes:

| Classe | Papel |
| --- | --- |
| `Frenet\Quote\Builder` | monta a `Request` só com os itens que exigem envio (`shipping = 1`). Converte peso e medidas para as classes de quilo e centímetro escolhidas no admin. Medidas abaixo dos mínimos do admin sobem para o mínimo, com 2 casas; o peso padrão só entra quando o produto não tem peso, e o peso perde apenas o ruído da conversão (6 casas), para item leve não virar zero. O valor declarado é a soma do `total` desses itens: preço de venda × quantidade, sem cupom e sem imposto |
| `Frenet\Quote\Catalog` | busca, para os produtos com envio do carrinho, o SKU (ou o modelo, quando o SKU está vazio) e os nomes das categorias ativas da loja atual, no idioma da loja, separados por `\|`. Se a consulta falhar, a cotação segue com o modelo e sem categoria |
| `Frenet\Quote\Request` e `Item` | corpo do `POST /shipping/quote` |
| `Frenet\Api` | responde do cache quando a mesma cotação foi feita há menos de 10 minutos. Senão, envia a cotação com o token e o `User-Agent` com a versão do módulo. Repete timeout, falha de conexão, 5xx e JSON inválido até o número de tentativas, com 300 ms entre elas; nunca repete 4xx. Esgotadas as tentativas, lança `Frenet\Quote\UnavailableException` |
| `Frenet\Quote\Cache` | guarda no `$this->cache` do OpenCart o corpo da resposta com pelo menos um serviço, mesmo que outros tenham vindo com `Error`. A chave é o hash do token e do corpo da requisição, então outra conta, outro CEP ou qualquer mudança no carrinho ou nas configurações enviadas consulta a Frenet de novo. Falha, 4xx, JSON inválido e resposta sem serviço nunca entram no cache. O cache do core expira tudo pelo `cache_expire` da loja (1 hora por padrão), por isso a entrada leva a própria validade de 10 minutos (`Cache::TTL`). O cache é só um atalho: se ler ou gravar falhar (no PHP 8, o adaptador de arquivos do core lança `TypeError` com a pasta sem escrita e `ValueError` ao ler um arquivo vazio durante uma gravação), ou se a resposta guardada não puder ser lida, a `Api` registra o erro e consulta a Frenet. Com o adaptador de arquivos, cada cotação diferente vira um arquivo `cache.frenet.quote.*` em `DIR_CACHE`, apagado pelo core depois do `cache_expire` |
| `Frenet\Quote\Parser`, `Result` e `Service` | leem os serviços da resposta, mesmo quando vem um só. Separam os que podem ser oferecidos dos que vieram com `Error`, guardando o `Msg`, e leem o `Message` da resposta. `Service::getTitle()` monta o nome do frete com a mensagem do prazo, somando os dias adicionais do admin ao `DeliveryTime`, mesmo quando a transportadora não informa o prazo (como o WooCommerce) |

O model `catalog/model/extension/shipping/frenet.php` só cota quando o endereço está na região geográfica escolhida no admin (Todas as regiões = qualquer endereço), com a mesma consulta a `zone_to_geo_zone` dos métodos Peso e Item do core. Se a região escolhida for excluída, o frete Frenet continua escondido, como no core, mas o erro vai para o `frenet.log` e o admin mostra um alerta. O grupo de impostos do admin vai no `tax_class_id` de cada serviço. Com o prazo ligado e a mensagem em branco, o nome usa `text_delivery_time` do idioma da loja.

O admin só salva com CEP de origem de 8 dígitos, aceitando ponto, hífen e espaço, e grava no formato `00000-000` (`Frenet\Config::parsePostcode()`). Também recusa habilitar o módulo sem token salvo ou digitado, com a mensagem embaixo do campo. Em caso de erro, o formulário volta com os valores enviados, menos o token, que nunca é exibido. O limite de simulações por IP aceita inteiros a partir de 0 (outro valor é ajustado com aviso) e, em branco, fica em 60.

### Simulador na página do produto

| Classe | Papel |
| --- | --- |
| `Frenet\Postcode` | tira a máscara do CEP (8 dígitos, aceitando ponto, hífen e espaço) e acha o estado pelas faixas dos Correios. Também é usada pelo CEP de origem do admin |
| `Frenet\Simulator\Product` | monta o produto da página como o `Cart::getProducts()` do core o devolveria sozinho no carrinho: mesma consulta de produto (loja, idioma, data de disponibilidade e status), desconto por quantidade e preço especial do grupo do cliente, e preço e peso das opções `select`, `radio` e `checkbox`. Lista as opções obrigatórias vazias, como o carrinho faz ao adicionar. Na página do produto, `requiresShipping()` só confere com uma consulta se a loja vende o produto e se ele exige envio |
| `Frenet\Simulator\RateLimit` | conta as simulações de cada IP por minuto no `$this->cache` do OpenCart, com a chave pelo hash do IP (ou da rede /64 do IPv6). Acima do limite do admin, devolve os segundos até o fim do minuto e registra o bloqueio no `frenet.log` uma vez por minuto. Limite 0 desliga a contagem. Se o cache falhar, registra o erro e deixa simular |

O controller `catalog/controller/extension/shipping/frenet.php` tem duas rotas:

- `addSimulator`: responde ao evento `catalog/view/product/product/after` e coloca o simulador logo depois do bloco do botão `#button-cart`. Só aparece com o módulo e o simulador ligados, token salvo e produto que exige envio. Num tema sem `#button-cart`, o simulador não aparece.
- `quote`: a rota AJAX. Recebe os mesmos campos que o botão de comprar envia (`product_id`, `quantity`, `option[...]`) e o `postcode`. Acha o país Brasil e o estado do CEP pelos models `localisation/country` e `localisation/zone` do core, que guardam as listas no cache, ajusta os endereços de imposto como a estimativa de frete do carrinho do core (`extension/total/shipping`) e cota pelo mesmo model do checkout (`getProductQuote`), com o cupom da sessão. Acima do limite, responde 429 com `Retry-After`.

O evento é registrado no `install()` do admin. Lojas que atualizam o módulo já estão com ele instalado, então o admin também registra o evento quando a configuração da Frenet é aberta por um usuário com permissão de alterar a extensão, se ele ainda não existir. O `uninstall()` apaga o evento.

O IP é o `REMOTE_ADDR`. O módulo não lê `X-Forwarded-For`, porque qualquer cliente pode enviar esse header e escapar do limite. Um endereço IPv6 conta pela sua rede /64, porque cada cliente IPv6 costuma ter a /64 inteira e trocaria de endereço a cada simulação. Um IPv6 mapeado (`::ffff:203.0.113.7`) conta como o IPv4.

O limite é de melhor esforço: o cache do OpenCart não tem incremento atômico, então simulações paralelas do mesmo IP podem passar um pouco do limite antes de o contador chegar nelas.

A versão do módulo fica em `Frenet\Module::VERSION`.

Ao criar uma classe nova, rode `composer dump-autoload` antes dos testes: o autoload de desenvolvimento é um classmap de `system/library/frenet/`.

## Padrão de código

O módulo segue o [padrão de código do OpenCart](https://github.com/opencart/opencart/wiki/Coding-standards): tabs, variáveis e arquivos em snake_case, classes e métodos em camelCase. O `.php-cs-fixer.php` é o da versão 3.0.5.1 do OpenCart, com três regras desligadas porque gerariam sintaxe acima do PHP 7.1 (`??=`, heredoc indentado e vírgula no fim de argumentos).

Os arquivos legados em `upload/admin` e `upload/catalog` entram no fixer à medida que forem reescritos.

Para o PHPStan, `tests/stubs/engine.php` imita o `Controller` e o `Model` do OpenCart 3, que leem os objetos do registry por `__get`, e declara com `@property` cada objeto que o módulo usa (`config`, `db`, `load`, `model_setting_setting`...). Ao usar um objeto novo do registry no controller ou no model, declare-o ali.

As classes de teste são `final`, como nos exemplos da documentação do PHPUnit, e por isso o fixer do OpenCart exige `self::` nas asserções. Nos testes, casos da mesma regra com entradas diferentes usam `#[DataProvider]` ou `#[TestWith]`, como recomenda a [documentação do PHPUnit](https://docs.phpunit.de/en/11.5/writing-tests-for-phpunit.html#data-providers), com um nome por caso. O fixer exige que o provider se chame `provide<Teste>Cases`.

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
| `composer phpstan` | PHPStan nível 6: `system/library/frenet`, os controllers do admin e da loja e o model de frete analisados como PHP 7.1 (`phpstan.neon.dist`), e `tests/` como PHP 8.2, o mínimo do PHPUnit 11 (`phpstan.tests.neon.dist`) |
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
| `POST /shipping/quote` com `token: single-service` | 200 só com SEDEX (`tests/mock/fixtures/quote-single.json`) |
| `POST /shipping/quote` com `token: empty` | 200 sem serviços, com `Message` (`tests/mock/fixtures/quote-empty.json`) |
| `POST /shipping/quote` com `token: with-errors` | 200 com SEDEX, Jadlog sem `DeliveryTime` e Mini Envios com `Error` (`tests/mock/fixtures/quote-errors.json`) |
| `POST /shipping/quote` com `token: flaky` | alterna 503 e 200, começando pelo 503; o contador fica em `frenet-mock-flaky`, no diretório temporário do mock |
| `POST /shipping/quote` com `token: status-503` (ou outro código) | responde com esse status HTTP |
| `/mock/status/{código}` | responde com esse status HTTP |
| `/mock/delay/{segundos}` | espera antes de responder, para testar tempo limite |
| `/mock/echo` | devolve método, headers e corpo recebidos |

Para usar fora dos testes:

```bash
php -S 127.0.0.1:8765 tests/mock/router.php
```

Com a variável `FRENET_MOCK_LOG` apontando para um arquivo, o mock grava ali os headers e o corpo de cada `POST /shipping/quote` recebido, uma linha JSON por requisição. Isso serve para contar as tentativas.

## Ambientes de teste

Os ambientes vêm do [instalador do OpenCart 3](https://github.com/Frenet-BR/opencart-v3-enviroment-installation). Cada demanda é testada nas versões que cobrem as faixas da tabela de compatibilidade:

| OpenCart | PHP |
| --- | --- |
| 3.0.0.0 | 7.1 |
| 3.0.3.3 | 7.4 |
| 3.0.3.8 | 7.4 |
| 3.0.5.1 | 8.2 |

```bash
OC_VERSION=3.0.0.0 OC_PHP_VERSION=7.1 SITE_URL=http://localhost:8300 ./install.sh
```

A 3.0.3.3 representa o fim da primeira faixa: a 3.0.3.4 só tem pacotes de pré-lançamento no GitHub, que não batem com o código da tag, e o instalador a recusa.

O pacote é instalado pelo admin, em **Extensões → Instalador**.

## Branches e commits

- `master`: só recebe versões publicadas.
- `3.0-develop`: integração; cada demanda entra aqui por PR.
- Uma branch por demanda, a partir da `3.0-develop`, com o número da issue do roadmap (ex.: `feature/889-base-tecnica`).
- Commits em inglês com gitmoji, uma responsabilidade por commit (ex.: `:sparkles: Add the cURL transport for the Frenet API`).
