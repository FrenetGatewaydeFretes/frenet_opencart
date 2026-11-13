![Frenet_logo_painel.png](https://painel.frenet.com.br/Content/images/Frenet_logo_painel.png)
# [Frenet - Gateway de fretes para e-commerce](https://www.frenet.com.br) #

## -- Módulo Frenet para OpenCart 3 -- ##

### Links ###
* [Painel Administrativo](https://painel.frenet.com.br)
* [Central de Ajuda](https://frenet.zendesk.com/)
* [Loja de Aplicativos OpenCart](https://www.opencart.com/index.php?route=marketplace/extension/info&extension_id=26072)

Antes de começar
------------------------

* Cadastre-se no [painel Frenet](https://painel.frenet.com.br/) e gere o **token** da loja. Sem token, a Frenet não cota.
* Cadastre o peso e as medidas dos produtos. A Frenet cota pelo peso e pelas medidas de cada item.
* O módulo funciona no OpenCart 3.0 (testado na 3.0.0.0, 3.0.3.3, 3.0.3.8 e 3.0.5.1), com PHP 7.1 ou mais novo e a extensão cURL.

Instalação: loja que ainda não tem a Frenet
------------------------

1. Em **Extensões → Instalador**, clique em **Enviar**, escolha o arquivo `frenet.ocmod.zip` e aguarde a mensagem **Concluído**.
2. Em **Extensões → Extensões**, escolha o tipo **Frete**. Na linha **Frenet**, clique no botão verde **Instalar**.
3. Na mesma linha, clique no lápis (**Editar**). Preencha o **Token**, o **CEP de origem** e mude a **Situação** para **Habilitado**. Clique em **Salvar**.
4. Confira na loja: a página do produto mostra **Calcule o frete** logo abaixo do botão Comprar, e o checkout mostra os fretes Frenet.

Atualização: loja que já tem a Frenet instalada
------------------------

> **⚠️ Importante: depois de enviar o pacote novo, abra a configuração da Frenet no admin.**
>
> Até a configuração ser aberta, o simulador de frete **não aparece** na página do produto. Se a loja usava a versão antiga do módulo, com **Chave** e **Senha**, o frete Frenet também **não aparece no checkout** até o token ser salvo.

1. Em **Extensões → Instalador**, clique em **Enviar**, escolha o arquivo `frenet.ocmod.zip` e aguarde a mensagem **Concluído**.
   * **Não desinstale a Frenet antes de atualizar.** Desinstalar apaga toda a configuração da Frenet, inclusive o token.
2. Em **Extensões → Extensões**, escolha o tipo **Frete** e clique no lápis (**Editar**) da linha **Frenet**.
   * Entre com um usuário do grupo **Administrador**, ou de um grupo com permissão de **modificar** a extensão Frenet. Abrir a configuração com um usuário que só tem permissão de acesso não conclui a atualização.
3. Se a tela avisar que a **Chave e a Senha da versão anterior foram removidas**, gere o token no painel Frenet, cole no campo **Token** e clique em **Salvar**.
4. Confira os campos novos **Simulador na página do produto** e **Limite de simulações por IP** e clique em **Salvar**.
5. Confira na loja: a página do produto mostra **Calcule o frete**, e o checkout mostra os fretes Frenet.

**Por que abrir a configuração?** O instalador do OpenCart 3 só copia os arquivos do pacote: ele não executa nenhum código do módulo. Numa instalação nova, o botão **Instalar** prepara o simulador. Numa atualização, o botão Instalar não aparece, porque a Frenet já está instalada, e quem prepara o simulador é a tela de configuração: ela registra o simulador na página do produto (o evento `frenet_simulator`) e cria a tabela do limite de simulações. Isso acontece uma vez só; depois, a loja não precisa de nenhum passo a mais.

Configuração
------------------------

| Campo | O que faz |
| --- | --- |
| Token | Token gerado no painel Frenet. Por segurança, o token salvo nunca é exibido: deixe em branco para mantê-lo. |
| CEP de origem | CEP de onde os pedidos saem, com 8 dígitos. |
| Exibir prazo | Mostra no nome do frete o prazo da Frenet mais os dias adicionais. |
| Mensagem do prazo de entrega | Nome do frete na loja: `%s` é o serviço e `%d` os dias. Exemplo: `%s - Em média %d dia(s)`. |
| Dias adicionais | Dias somados ao prazo de todos os serviços, como o tempo para separar o pedido. |
| Classe de peso em quilos | Classe de peso da loja que corresponde ao quilograma. |
| Classe de medida em centímetros | Classe de medida da loja que corresponde ao centímetro. |
| Altura, largura e comprimento mínimos | Menores medidas enviadas à Frenet. Padrão: 2 × 11 × 16 cm, as mínimas dos Correios. |
| Peso padrão | Peso enviado para o produto cadastrado sem peso. Padrão: 1 kg. |
| Divisão em pacotes | Cota em pacotes o carrinho mais pesado que o peso máximo por pacote. Vem desligada. Veja [Divisão em pacotes](#divisão-em-pacotes). |
| Peso máximo por pacote | Peso de cada pacote na divisão. Padrão: 30 kg, o limite dos Correios. |
| Tempo limite e Tentativas | Quanto cada cotação espera a Frenet responder (2 a 10 s) e quantas vezes tenta (1 a 3). |
| Simulador na página do produto | Liga e desliga o campo **Calcule o frete** na página do produto. Vem ligado. |
| Limite de simulações por IP | Quantas simulações cada visitante pode fazer por minuto. Padrão: 60. Use 0 para não limitar. |
| Taxa de produtos virtuais | Liga e desliga a taxa cobrada nos pedidos com produtos sem envio. Vem desligada. Veja [Taxa de produtos virtuais](#taxa-de-produtos-virtuais). |
| Valor da taxa | Valor fixo cobrado uma vez por pedido, na moeda padrão da loja, como `4,90`. Precisa ser maior que zero; pode ficar em branco com a taxa desligada. |
| Nome da taxa | Nome que o comprador vê no carrinho, no checkout e no pedido. Em branco, usa **Taxa de processamento do pedido**. |
| Debug | Grava cada cotação no `frenet.log`, sem o token. Os erros são gravados sempre. |
| Grupo de impostos | Imposto aplicado ao frete Frenet. |
| Região geográfica | O frete Frenet só aparece para endereços desta região. |
| Situação | Habilita a Frenet. Só habilita com o token preenchido. |
| Posição | Ordem da Frenet entre as formas de entrega. |

Simulador de frete na página do produto
------------------------

O comprador digita o CEP na página do produto e vê os fretes Frenet com o preço e o prazo que o carrinho mostraria para aquele produto, quantidade e opções. O simulador usa a região geográfica, o grupo de impostos e as regras de preço do produto como o checkout.

Cada visitante pode simular até o **Limite de simulações por IP** por minuto. A loja atrás do Cloudflare, de um balanceador ou de um proxy na própria rede conta cada visitante pelo IP dele, sem nenhuma configuração no servidor.

Divisão em pacotes
------------------------

Os Correios não cotam um pacote acima de 30 kg. Sem a divisão, um carrinho mais pesado que isso fica sem PAC e SEDEX no checkout. Com a **Divisão em pacotes** ligada, um carrinho acima do **Peso máximo por pacote** é dividido em pacotes e cotado assim:

* **Correios:** cada pacote é cotado à parte. O serviço aparece com a soma dos preços dos pacotes e o maior prazo entre eles, e só se todos os pacotes foram cotados: se um pacote não tiver PAC, o PAC não aparece.
* **Demais transportadoras** (Jadlog, Loggi, J&T...): cotadas com o carrinho inteiro, como sem a divisão.
* As cotações do carrinho inteiro e dos pacotes saem ao mesmo tempo, então a etapa de frete leva quase o mesmo tempo de uma cotação só.
* Os produtos entram nos pacotes na ordem do carrinho, enchendo um pacote antes de abrir o próximo. Cada pacote declara o valor dos próprios produtos.
* O carrinho é cotado inteiro, sem dividir, quando um único produto pesa mais que o pacote ou quando seriam necessários mais de 10 pacotes. O motivo vai para o `frenet.log`.
* O peso é sempre comparado em quilos, mesmo numa loja configurada em libras.
* O simulador da página do produto também divide, para mostrar o mesmo frete que o carrinho.

Taxa de produtos virtuais
------------------------

Cobra uma taxa fixa, uma vez por pedido, quando o carrinho tem um produto sem envio, como um e-book, um serviço ou um download. Produto sem envio é o que tem **Precisa de frete?** (*Requires Shipping*) em **Não**, na aba **Dados** do produto.

* **Carrinho com produtos com e sem envio:** a taxa é somada a cada frete Frenet e aparece no nome dele, por exemplo `PAC - Em média 7 dia(s) + Taxa de processamento do pedido (R$4,90)`. Se o comprador escolher outra forma de entrega, a taxa não é cobrada.
* **Carrinho só com produtos sem envio:** o OpenCart pula a etapa de frete, então a taxa aparece numa linha própria dos totais, no carrinho, no checkout e no pedido.
* **Carrinho só com produtos com envio:** nenhuma taxa.

Para ligar:

1. Na configuração da Frenet, mude **Taxa de produtos virtuais** para **Habilitado**, preencha o **Valor da taxa** e, se quiser, o **Nome da taxa**. Clique em **Salvar**.
2. A mensagem de sucesso avisa que a taxa dos carrinhos sem envio foi instalada em **Extensões → Totais**, como **Frenet - taxa de produtos virtuais**. Esse total é instalado sozinho: não é preciso instalá-lo à mão. Nele, você só muda a **Posição** da linha da taxa nos totais (vem com a mesma posição do Frete).

Regras da taxa:

* A Frenet precisa estar com a **Situação** **Habilitado**.
* A taxa recebe o **Grupo de impostos** do frete Frenet.
* A **Região geográfica** da Frenet vale também para a taxa. No carrinho só com produtos sem envio, que não tem endereço de entrega, vale o endereço de pagamento; antes de o comprador informá-lo, vale o endereço da loja.
* Um cupom com frete grátis também tira a taxa: no carrinho misto, o cupom desconta o frete Frenet com a taxa; no carrinho só com produtos sem envio, a linha da taxa não aparece.
* O simulador da página do produto não soma a taxa: ele calcula o frete do produto da página, que tem envio.

Problemas comuns
------------------------

**O simulador não aparece na página do produto.** Confira, nesta ordem:

1. Depois de atualizar o módulo, a configuração da Frenet foi aberta por um usuário com permissão de modificar a extensão? Veja [Atualização](#atualização-loja-que-já-tem-a-frenet-instalada).
2. A **Situação** está **Habilitado** e o **Token** está preenchido?
3. O campo **Simulador na página do produto** está **Habilitado**?
4. O produto exige envio? Na aba **Dados** do produto, o campo **Precisa de frete?** (*Requires Shipping* no admin em inglês) precisa estar em **Sim**. Produtos sem envio não mostram o simulador.
5. Em **Extensões → Eventos**, o evento `frenet_simulator` está habilitado?
6. O tema usa o botão **Comprar** padrão do OpenCart (`id="button-cart"`)? O simulador aparece logo abaixo dele; num tema sem esse botão, ele não aparece.

**O frete Frenet não aparece no checkout.** Confira o token, a **Situação**, o **CEP de origem**, a **Região geográfica** e o peso e as medidas dos produtos. Ligue o **Debug** e veja o arquivo `frenet.log` na pasta de logs do OpenCart (normalmente `system/storage/logs/`).

**A taxa de produtos virtuais não aparece.** Confira:

1. O campo **Taxa de produtos virtuais** está **Habilitado**, com o **Valor da taxa** maior que zero, e a **Situação** da Frenet está **Habilitado**?
2. O produto está com **Precisa de frete?** em **Não**? Só esses produtos geram a taxa.
3. No carrinho com produtos com envio, o comprador escolheu um frete Frenet? Outras formas de entrega não somam a taxa.
4. No carrinho só com produtos sem envio, o total **Frenet - taxa de produtos virtuais** aparece instalado e habilitado em **Extensões → Totais**? Se alguém o desinstalou, abra a configuração da Frenet e clique em **Salvar** com a taxa ligada: ele é instalado de novo.
5. O endereço de pagamento está na **Região geográfica** da Frenet?
6. O comprador usou um cupom com frete grátis? Ele tira a taxa também.

**Os Correios não aparecem num carrinho pesado.** Ligue a **Divisão em pacotes**. Com ela ligada, confira no `frenet.log` se o carrinho foi cotado inteiro porque um produto pesa mais que o pacote ou porque seriam mais de 10 pacotes, e confira o peso dos produtos.

**O simulador pede para aguardar um minuto.** O visitante passou do **Limite de simulações por IP**. Aumente o limite ou use 0 para não limitar.

Desinstalação
------------------------

Em **Extensões → Extensões → Frete**, clique em **Desinstalar** na linha **Frenet**. A desinstalação remove o simulador, a tabela do limite, o total **Frenet - taxa de produtos virtuais** e toda a configuração da Frenet, inclusive o token.

Observações
------------------------
* Para o correto funcionamento do módulo é essencial o cadastro correto das dimensões e pesos dos produtos e do CEP de origem.
