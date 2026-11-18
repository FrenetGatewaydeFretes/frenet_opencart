# Changelog

O que muda para o lojista em cada versão do módulo Frenet para OpenCart 3. O pacote de cada versão é o `frenet.ocmod.zip` anexado ao [release](https://github.com/FrenetGatewaydeFretes/frenet_opencart/releases) no GitHub, e também está no [marketplace do OpenCart](https://www.opencart.com/index.php?route=marketplace/extension/info&extension_id=26072).

## 3.0.0

O módulo foi reescrito para voltar a cotar. A Frenet desativou o webservice com Chave e Senha que as versões anteriores usavam. Desde então, essas versões não mostram nenhum frete Frenet. Esta versão cota pela API da Frenet com o token da loja e funciona em todo o OpenCart 3, da 3.0.0.0 à 3.0.5.1, com PHP 7.1 a 8.5 e a extensão cURL.

**Ao atualizar:** envie o pacote em **Extensões → Instalador** sem desinstalar a Frenet. Depois, abra a configuração da Frenet no admin, cole o token gerado no [painel Frenet](https://painel.frenet.com.br/) e salve. O passo a passo está no [README](https://github.com/FrenetGatewaydeFretes/frenet_opencart#atualização-loja-que-já-tem-a-frenet-instalada).

### Cotação

- Campo **Token** no lugar de **Chave** e **Senha**, que são apagadas na atualização. O token salvo nunca aparece na tela. O módulo não precisa mais da extensão `php-soap`.
- A cotação com um único serviço aparece no checkout.
- Se a Frenet estiver fora do ar ou lenta, a etapa de frete continua funcionando: o comprador vê um aviso e pode escolher outra forma de entrega. Novos campos **Tempo limite** e **Tentativas**.
- Os erros de cotação vão sempre para o `frenet.log`. Com o novo campo **Debug**, cada cotação também vai para o log, sem o token.
- A mesma cotação feita de novo em até 10 minutos aparece na hora, sem consultar a Frenet de novo.

### Produtos e valor declarado

- Produtos sem envio, como serviços e downloads, ficam fora do peso, das medidas e do valor declarado.
- Peso e medidas são convertidos das unidades da loja para quilos e centímetros. Produto sem peso usa o **Peso padrão** (1 kg), e medidas menores que as mínimas dos Correios usam a **Altura**, a **Largura** e o **Comprimento mínimos** (2 × 11 × 16 cm).
- O valor declarado é o preço de venda dos produtos com envio, com promoção e sem descontar o cupom.
- Cada produto vai com o SKU e as categorias, para as regras do painel Frenet.

### Regras da loja

- **Exibir prazo**, **Mensagem do prazo de entrega** e **Dias adicionais** controlam o nome e o prazo do frete.
- O **Grupo de impostos** passa a ser aplicado ao frete, e a **Região geográfica** limita os endereços que veem o frete Frenet.
- O admin valida o CEP de origem e não habilita a Frenet sem token. As mensagens de erro aparecem embaixo de cada campo.

### Recursos novos

- **Simulador de frete na página do produto**, ligado por padrão, com limite de simulações por IP.
- **Taxa de produtos virtuais**, desligada por padrão: cobra uma taxa fixa por pedido quando o carrinho tem produto sem envio.
- **Divisão em pacotes**, desligada por padrão: divide o carrinho acima do peso máximo por pacote (padrão 30 kg) e cota os Correios por pacote, como o módulo da Frenet para Magento 2.

### Idiomas

- Admin e loja em português (pt-br) e inglês (en-gb).
