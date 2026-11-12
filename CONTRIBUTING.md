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

O OpenCart aplica `htmlspecialchars` em todo `$this->request->post` e `get`, e a configuração é gravada assim, já escapada. Um campo de texto livre do admin (como a mensagem do prazo e o nome da taxa) volta ao formulário sem o filtro `escape`, como nos templates do core: com o filtro, cada vez que o lojista salva, um `&` vira `&amp;`, depois `&amp;amp;`, e assim por diante.

O OpenCart 3 roda o Twig com `autoescape` desligado, então todo valor impresso no template leva `|escape`.

Em versões antigas do OpenCart 3, como a 3.0.3.3, o catálogo e o admin guardam o template compilado e não conferem se o arquivo mudou (o Twig roda sem `auto_reload`). Por isso o admin e o simulador renderizam os templates da Frenet com `template_cache` desligado. Sem isso, depois de uma atualização do módulo, a loja mostraria o template antigo até o lojista limpar o cache de tema. A chave só tem efeito nessas versões: a 3.0.0.0 não guarda o template compilado, e da 3.0.3.8 em diante o core guarda sempre, com `auto_reload`, sem ler `template_cache`. Onde ela vale, o simulador recompila o template a cada página de produto, um custo de poucos milissegundos.

O JavaScript do simulador roda com o jQuery do tema padrão, do 2.1.1 (3.0.0.0) ao 3.7.1 (3.0.5.1), e o Bootstrap 3. Ele não usa recursos que só existem em versões mais novas, e textos vindos do servidor entram na página por `.text()`.

## Estrutura

```
upload/                         vai para o .ocmod.zip; espelha a raiz da loja
  admin/...                     frete: controller, model da tabela do limite do simulador, idiomas e template;
                                total: tela do total da taxa de produtos virtuais
  catalog/...                   frete: model, controller e template do simulador, idiomas da loja;
                                total: model da taxa de produtos virtuais
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
| `Frenet\Quote\Cache` | guarda no `$this->cache` do OpenCart o corpo da resposta com pelo menos um serviço, mesmo que outros tenham vindo com `Error`. A chave é o hash do token e do corpo da requisição, então outra conta, outro CEP ou qualquer mudança no carrinho ou nas configurações enviadas consulta a Frenet de novo. Falha, 4xx, JSON inválido e resposta sem serviço nunca entram no cache. O cache do core expira tudo pelo `cache_expire` da loja (1 hora por padrão), por isso a entrada leva a própria validade de 10 minutos (`Cache::TTL`). O cache é só um atalho: se ler ou gravar falhar (no PHP 8, o adaptador de arquivos do core lança `TypeError` com a pasta sem escrita e `ValueError` ao ler um arquivo vazio durante uma gravação), ou se a resposta guardada não puder ser lida, a `Api` registra o erro e consulta a Frenet. Os avisos de PHP do adaptador de arquivos durante a leitura e a gravação não chegam à página: da 3.0.0.0 à 3.0.3.x, duas cotações iguais ao mesmo tempo disputam o mesmo arquivo, e com a exibição de erros ligada o aviso sairia antes do JSON do simulador. Com o adaptador de arquivos, cada cotação diferente vira um arquivo `cache.frenet.quote.*` em `DIR_CACHE`, apagado pelo core depois do `cache_expire`. Com `Cache($cache, false)` só lê: o simulador usa assim quando o `cache_engine` da loja é `file` |
| `Frenet\Quote\Parser`, `Result` e `Service` | leem os serviços da resposta, mesmo quando vem um só. Separam os que podem ser oferecidos dos que vieram com `Error`, guardando o `Msg`, e leem o `Message` da resposta. `Service::getTitle()` monta o nome do frete com a mensagem do prazo, somando os dias adicionais do admin ao `DeliveryTime`, mesmo quando a transportadora não informa o prazo (como o WooCommerce) |

O model `catalog/model/extension/shipping/frenet.php` só cota quando o endereço está na região geográfica escolhida no admin (Todas as regiões = qualquer endereço), com a mesma consulta a `zone_to_geo_zone` dos métodos Peso e Item do core (`Frenet\GeoZone`). Se a região escolhida for excluída, o frete Frenet continua escondido, como no core, mas o erro vai para o `frenet.log` e o admin mostra um alerta. O grupo de impostos do admin vai no `tax_class_id` de cada serviço. Com o prazo ligado e a mensagem em branco, o nome usa `text_delivery_time` do idioma da loja.

O admin só salva com CEP de origem de 8 dígitos, aceitando ponto, hífen e espaço, e grava no formato `00000-000` (`Frenet\Postcode::format()`). Também recusa habilitar o módulo sem token salvo ou digitado, com a mensagem embaixo do campo. Em caso de erro, o formulário volta com os valores enviados, menos o token, que nunca é exibido. O limite de simulações por IP só salva inteiros a partir de 0. Um valor negativo ou com casas decimais não salva, com a mensagem embaixo do campo, que o admin também mostra enquanto o lojista digita. Em branco, o limite fica em 60.

### Simulador na página do produto

| Classe | Papel |
| --- | --- |
| `Frenet\Postcode` | tira a máscara do CEP (8 dígitos, aceitando ponto, hífen e espaço), formata como `00000-000` e acha o estado pelas faixas dos Correios. Também é usada pelo CEP de origem do admin |
| `Frenet\Simulator\Product` | monta o produto da página como o `Cart::getProducts()` do core o devolveria sozinho no carrinho: mesma consulta de produto (loja, idioma, data de disponibilidade e status), desconto por quantidade e preço especial do grupo do cliente, e preço e peso das opções `select`, `radio` e `checkbox`. Lista as opções obrigatórias vazias, como o carrinho faz ao adicionar, e devolve a quantidade mínima do produto (`minimum`, no mínimo 1). Na página do produto, `requiresShipping()` só confere com uma consulta se a loja vende o produto e se ele exige envio |
| `Frenet\Simulator\ClientIp` | devolve o IP do visitante: atrás de um proxy da rede da loja, o último endereço público do `X-Forwarded-For`; vindo de um endereço do Cloudflare, o `CF-Connecting-IP`; senão o `REMOTE_ADDR` |
| `Frenet\Simulator\Origin` | diz se o navegador enviou a simulação de outro site, pelo host do `Origin` comparado com o `Host` da requisição e com as URLs da loja (`config_url` e `config_ssl`), ou, sem `Origin`, pelo `Sec-Fetch-Site: cross-site`. Comparar também com as URLs da loja evita recusar a própria página quando um proxy troca o `Host` |
| `Frenet\Simulator\RateLimit` | conta as simulações de cada IP por minuto na tabela `frenet_simulator_limit`, com a chave pelo hash do IP (ou da rede /64 do IPv6). Um único `INSERT ... ON DUPLICATE KEY UPDATE` soma a simulação e guarda em `LAST_INSERT_ID()` a contagem desta requisição, então simulações paralelas do mesmo IP recebem contagens diferentes e nenhuma passa do limite. Acima do limite do admin, devolve os segundos até o fim do minuto e registra o bloqueio no `frenet.log` uma vez por minuto. Em 1 de cada 20 vezes que um IP começa um minuto, lê até 100 IPs cujo minuto já acabou e os apaga pela chave, sem travar um intervalo do índice, para a limpeza não fazer as simulações de outros IPs esperarem. Limite 0 desliga a contagem. Se a gravação falhar, roda `RateLimit::createTable()` (o mesmo `CREATE TABLE IF NOT EXISTS` do admin) e tenta de novo, porque subir uma versão nova do módulo não roda o `install()`. Se ainda falhar, registra o primeiro erro e deixa simular |

O controller `catalog/controller/extension/shipping/frenet.php` tem três rotas:

- `addSimulator`: responde ao evento `catalog/view/product/product/after` e coloca o simulador logo depois do bloco do botão `#button-cart`. Só aparece com o módulo e o simulador ligados, token salvo e produto que exige envio. Num tema sem `#button-cart`, o simulador não aparece.
- `postcode`: devolve em JSON, no formato `00000-000`, o CEP de entrega da sessão ou, para o cliente logado sem CEP na sessão, o do endereço padrão da conta. O simulador chama a rota quando a página carrega só para o cliente logado. Para o visitante, chama quando ele entra no campo, para a página do produto não fazer uma segunda requisição a cada visita. O CEP não vai no HTML, porque uma extensão de cache de página inteira mostraria o CEP de um comprador para outros; o HTML leva só a marca `data-autofill` de cliente logado, que não identifica ninguém. A resposta sai com `Cache-Control: no-store, private`.
- `quote`: a rota AJAX da simulação. Recebe os mesmos campos que o botão de comprar envia (`product_id`, `quantity`, `option[...]`) e o `postcode`. Acha o país Brasil e o estado do CEP pelos models `localisation/country` e `localisation/zone` do core, que guardam as listas no cache, ajusta os endereços de imposto como a estimativa de frete do carrinho do core (`extension/total/shipping`) e cota pelo mesmo model do checkout (`getProductQuote`), com o cupom da sessão. Antes do limite, responde 403 a uma simulação que o navegador enviou de outro site (`Simulator\Origin`): sem isso, qualquer site faria os navegadores dos seus visitantes simularem na loja, cada um com o próprio IP, e o limite por IP não seguraria. Acima do limite, responde 429 com `Retry-After`. Confere a loja vender o produto antes das opções obrigatórias, para não contar os nomes das opções de um produto desativado ou de outra loja, e abaixo da quantidade mínima do produto pede a mínima, como o carrinho. A cotação do simulador consulta a Frenet uma vez só, sem as tentativas do admin: com a API lenta ou fora do ar, cada clique prenderia um processo PHP por até 15,6 s com o timeout padrão, e compradores simulando durante a queda ocupariam os processos da loja inteira, checkout incluso. Com o `cache_engine` `file`, o simulador lê o cache de cotação mas não grava nele, porque cada simulação diferente deixaria um arquivo em `DIR_CACHE` por 1 hora e o core percorre essa pasta em toda página: a página de categoria levou 38 ms sem arquivos extras, 77 ms com 10 mil e 192 ms com 30 mil. Com Redis ou Memcached, grava como o checkout. Serviços com erro e respostas sem serviço da simulação só vão para o `frenet.log` com o debug ligado, porque cada simulação gravaria cerca de 0,6 KB; no checkout continuam como erro. Na página, o simulador manda um cálculo por vez: um novo pedido, pelo botão ou pelo Enter, enquanto o anterior carrega só é enviado quando ele termina, e só se o CEP ou o formulário do produto mudou. Assim a tabela nunca mostra o frete de um CEP anterior nem duas respostas. Antes de cada cálculo limpa as mensagens de campo de `#product`, inclusive as do botão de comprar, como o botão de comprar faz com as do simulador.

O evento e a tabela do limite são criados no `install()` do admin. Lojas que atualizam o módulo já estão com ele instalado, então o admin também cria o evento e a tabela quando a configuração da Frenet é aberta por um usuário com permissão de alterar a extensão, se ainda não existirem. A rota `quote` cria a tabela se não a encontrar, para o limite valer antes disso. O `uninstall()` apaga o evento e a tabela.

O IP é o `REMOTE_ADDR`, ou o `CF-Connecting-IP` quando o `REMOTE_ADDR` está nas faixas que o Cloudflare publica em https://www.cloudflare.com/ips/ (`ClientIp::CLOUDFLARE_RANGES`). Assim, numa loja atrás do Cloudflare, cada visitante conta separado sem nenhuma configuração no servidor. Fora dessas faixas o header é ignorado, porque qualquer cliente pode enviá-lo direto ao servidor e escapar do limite.

Atrás de um balanceador ou proxy da própria rede da loja (AWS ALB, nginx na frente do PHP, Varnish), toda requisição chega com o endereço do proxy, e a loja inteira dividiria o limite de um IP. Quando o `REMOTE_ADDR` é privado, de loopback, link-local ou do espaço compartilhado `100.64.0.0/10` (`ClientIp::INTERNAL_RANGES`), o IP é o último endereço do `X-Forwarded-For` fora dessas faixas: o proxy acrescenta ao fim do header o endereço de quem falou com ele, então um endereço digitado pelo visitante fica antes e é ignorado. Ninguém na internet chega ao PHP com um `REMOTE_ADDR` interno, então de fora o header não muda nada. Se esse endereço for do Cloudflare, vale o `CF-Connecting-IP`. Um item que não é IP (com porta, `unknown`) antes de achar o endereço público faz o módulo manter o `REMOTE_ADDR`. Não é coberta uma CDN de endereço público na frente da loja além do Cloudflare. Quando o Cloudflare publicar faixas novas, atualize a lista. Um endereço IPv6 conta pela sua rede /64, porque cada cliente IPv6 costuma ter a /64 inteira e trocaria de endereço a cada simulação. Um IPv6 mapeado (`::ffff:203.0.113.7`) conta como o IPv4.

O limite usa uma tabela própria, e não o `$this->cache` do OpenCart, por dois motivos. O cache não tem incremento atômico: com o cache em arquivo, 40 simulações paralelas do mesmo IP passaram até 10 com limite 3, e as corridas entre o `get()` e o `set()` do core imprimiam Warnings dentro do JSON. Além disso, o cache em arquivo guarda cada IP por 1 hora (`cache_expire`) num arquivo do `DIR_CACHE`, e o core percorre essa pasta a cada requisição da loja: com 10 mil IPs na última hora, toda página ficava cerca de 25 ms mais lenta (72 ms com 30 mil).

Medições da tabela com o código do módulo e o driver mysqli do OpenCart, no MariaDB 10.1 e 11.4 e no MySQL 5.7 e 8.0:

- Uma simulação custa uma gravação de uma linha, o mesmo que a sessão em banco do OpenCart grava a cada página, e a leitura de `LAST_INSERT_ID()`, sem tocar a tabela: cerca de 3 ms no MariaDB e 11 ms no MySQL 8 com `innodb_flush_log_at_trx_commit=1`, iguais com a tabela vazia ou com 1 milhão de linhas.
- 60 processos simultâneos do mesmo IP receberam as contagens de 1 a 60, sem repetir, nos quatro servidores.
- 50 clientes simultâneos fazendo 10 mil simulações sobre uma tabela de 20 mil linhas: p99 entre 16 ms (MariaDB 11.4) e 67 ms (MySQL 8.0), nenhum erro, e a limpeza levou a tabela para cerca de 10 mil linhas.
- Com 1 milhão de linhas acumuladas, 90% vencidas, as mesmas 10 mil simulações apagaram cerca de 30 mil linhas, com p99 abaixo de 300 ms.

A limpeza apaga as linhas pela chave, e não com `DELETE ... WHERE expires <= ...`: o `DELETE` por intervalo travava o índice `expires` e fazia as gravações de outros IPs esperarem, com p99 de 0,7 a 1,1 s e picos de 7 a 9 s no mesmo teste. Ela lê as linhas a partir de um ponto aleatório da chave, porque limpezas simultâneas que liam as linhas mais antigas pelo índice `expires` apagavam as mesmas linhas, e a tabela de 1 milhão crescia em vez de diminuir. Uma linha ocupa cerca de 250 bytes com os índices, e a tabela fica perto do número de IPs que simularam nos últimos minutos.

### Taxa de produtos virtuais

Taxa fixa cobrada uma vez por pedido quando o carrinho tem um produto com `shipping = 0` (`Frenet\VirtualFee`), ligada no admin com valor maior que zero. Os vales-presente do carrinho não são produtos e não contam.

- **Carrinho com produtos com e sem envio:** o model de frete soma a taxa ao `cost` de cada serviço Frenet e cita a taxa no nome (`text_frenet_virtual_fee_rate`: `PAC ... + Taxa de processamento do pedido (R$4,90)`). O valor no nome leva o imposto quando a loja mostra preços com imposto, como o preço do frete. Com outra forma de entrega, nenhuma taxa: o `title` e o `cost` gravados no pedido são os do frete escolhido.
- **Carrinho só com produtos sem envio:** o core pula a etapa de frete (`Cart::hasShipping()` falso), então nenhum método de frete roda. A taxa entra pelo total `extension/total/frenet` (`catalog/model/extension/total/frenet.php`), que soma a linha aos totais do carrinho, do checkout e do pedido, com o grupo de impostos da Frenet. Ele confere a região geográfica da Frenet pelo endereço de pagamento da sessão ou, antes de o comprador informá-lo, pelo endereço da loja, e só cobra com a Frenet habilitada. Um cupom com frete grátis válido na sessão (`getCoupon()` do total de cupom do core, com o total de cupom ligado) tira a taxa, como o cupom já tira o frete Frenet que a leva no carrinho misto. Num carrinho com envio, o total não faz nada.
- O simulador não soma a taxa: ele cota só o produto da página, que tem envio.

O OpenCart só roda totais instalados em **Extensões → Totais**, e a lista de Totais desinstala sozinha um total sem `admin/controller/extension/total/<código>.php`. Por isso existe a tela `extension/total/frenet`, que só tem a posição e um link para a configuração da Frenet. Quando o lojista salva a configuração com a taxa ligada e o total não está instalado, o admin de frete o instala como o core faz (`setting/extension` `install`, permissões de acesso e alteração para o grupo do usuário e o `install()` do total, que o habilita na posição do total de frete) e avisa na mensagem de sucesso. O `uninstall()` do frete desinstala o total junto.

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
