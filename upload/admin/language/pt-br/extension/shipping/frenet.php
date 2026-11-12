<?php
// Heading
$_['heading_title']         = 'Frenet';

// Text
$_['text_extension']        = 'Extensões';
$_['text_success']          = 'Frete Frenet modificado com sucesso!';
$_['text_edit']             = 'Editando Frenet';
$_['text_version']          = 'Módulo Frenet %s';
$_['text_migrated']         = 'A Chave e a Senha da versão anterior foram removidas: a Frenet agora autentica só pelo token.';
$_['text_token_required']   = 'Sem token, a Frenet não cota. Gere o token no painel Frenet e cole no campo Token.';
$_['text_token_saved']      = 'Token salvo. Deixe em branco para mantê-lo.';
$_['text_panel']            = 'Abrir o painel Frenet';
$_['text_adjusted']         = '%s ajustado para %d (aceita de %d a %d).';
$_['text_default_used']     = '%s precisa ser maior que zero: usado %s.';
$_['text_adjusted_min']     = '%s aceita só números inteiros a partir de 0: usado %d.';
$_['text_total_installed']  = 'A taxa dos carrinhos sem envio foi instalada em Extensões → Totais, como Frenet - taxa de produtos virtuais.';
$_['text_geo_zone_missing']  = 'A região geográfica escolhida para a Frenet foi excluída, e o frete Frenet não aparece para nenhum endereço. Escolha outra região e salve.';

// Entry
$_['entry_token']           = 'Token';
$_['entry_postcode']        = 'CEP de origem';
$_['entry_msg_prazo']       = 'Mensagem do prazo de entrega';
$_['entry_weight_class']    = 'Classe de peso em quilos';
$_['entry_length_class']    = 'Classe de medida em centímetros';
$_['entry_minimum_height']  = 'Altura mínima (cm)';
$_['entry_minimum_width']   = 'Largura mínima (cm)';
$_['entry_minimum_length']  = 'Comprimento mínimo (cm)';
$_['entry_default_weight']  = 'Peso padrão (kg)';
$_['entry_timeout']         = 'Tempo limite (segundos)';
$_['entry_attempts']        = 'Tentativas';
$_['entry_debug']           = 'Debug';
$_['entry_status']          = 'Situação';
$_['entry_sort_order']      = 'Posição';
$_['entry_delivery_time']   = 'Exibir prazo';
$_['entry_additional_days'] = 'Dias adicionais';
$_['entry_tax_class']       = 'Grupo de impostos';
$_['entry_geo_zone']        = 'Região geográfica';
$_['entry_simulator']       = 'Simulador na página do produto';
$_['entry_simulator_limit'] = 'Limite de simulações por IP';
$_['entry_virtual_fee']     = 'Taxa de produtos virtuais';
$_['entry_virtual_fee_amount'] = 'Valor da taxa';
$_['entry_virtual_fee_name'] = 'Nome da taxa';

// Help
$_['help_token']            = 'Token gerado no painel Frenet. Por segurança, o token salvo nunca é exibido.';
$_['help_msg_prazo']        = 'Nome do frete na loja: %s é o serviço e %d os dias de entrega. Em branco, usa a mensagem do exemplo.';
$_['help_weight_class']     = 'Classe de peso da loja que corresponde ao quilograma. O peso de cada produto é convertido para ela.';
$_['help_length_class']     = 'Classe de medida da loja que corresponde ao centímetro. As medidas de cada produto são convertidas para ela.';
$_['help_minimum_height']   = 'Menor altura enviada à Frenet: produto mais baixo ou sem altura vai com este valor. Padrão: 2 cm, a mínima dos Correios.';
$_['help_minimum_width']    = 'Menor largura enviada à Frenet: produto mais estreito ou sem largura vai com este valor. Padrão: 11 cm, a mínima dos Correios.';
$_['help_minimum_length']   = 'Menor comprimento enviado à Frenet: produto mais curto ou sem comprimento vai com este valor. Padrão: 16 cm, o mínimo dos Correios.';
$_['help_default_weight']   = 'Peso enviado à Frenet para o produto cadastrado sem peso. Padrão: 1 kg.';
$_['help_timeout']          = 'Quanto cada tentativa de cotação espera a Frenet responder, de 2 a 10 segundos.';
$_['help_attempts']         = 'Quantas vezes a cotação é enviada quando a Frenet não responde ou responde com erro do servidor, de 1 a 3. Token inválido e outros erros 4xx nunca são repetidos.';
$_['help_debug']            = 'Grava em frenet.log cada requisição e resposta da cotação, sem o token. Os erros são gravados sempre.';
$_['help_delivery_time']    = 'Mostra no nome do frete o prazo da Frenet mais os dias adicionais, com a mensagem abaixo.';
$_['help_additional_days']  = 'Dias somados ao prazo de todos os serviços Frenet, como o tempo para separar o pedido. Somados também quando a transportadora não informa o prazo.';
$_['help_geo_zone']         = 'O frete Frenet só aparece para endereços desta região. Em Todas as regiões, aparece para qualquer endereço.';
$_['help_simulator']        = 'Mostra na página do produto um campo de CEP que calcula os fretes Frenet do produto, com os preços e prazos do carrinho.';
$_['help_simulator_limit']  = 'Quantas simulações cada IP pode fazer por minuto. Acima disso, o simulador pede para aguardar e o bloqueio vai para o frenet.log. Use 0 para não limitar.';
$_['help_virtual_fee']      = 'Cobra uma taxa fixa, uma vez por pedido, quando o carrinho tem um produto sem envio (Precisa de frete? Não), como uma taxa de processamento. Com produtos com envio, a taxa é somada a cada frete Frenet; só com produtos sem envio, ela aparece numa linha dos totais do pedido.';
$_['help_virtual_fee_amount'] = 'Valor cobrado uma vez por pedido, na moeda padrão da loja, com o Grupo de impostos do frete Frenet.';
$_['help_virtual_fee_name'] = 'Nome mostrado ao comprador no carrinho, no checkout e no pedido. Em branco, usa o do exemplo.';
$_['placeholder_virtual_fee_amount'] = '4,90';
$_['placeholder_virtual_fee_name'] = 'Taxa de processamento do pedido';
$_['placeholder_msg_prazo'] = '%s - Em média %d dia(s)';

// Error
$_['error_permission']      = 'Atenção: você não tem permissão para modificar a extensão Frenet!';
$_['error_warning']         = 'Atenção: a configuração não foi salva. Corrija os campos destacados.';
$_['error_postcode']        = 'O CEP de origem precisa ter 8 dígitos, como 01310-100.';
$_['error_simulator_limit_value'] = 'Valores negativos não são permitidos. Use 0 ou um número inteiro positivo.';
$_['error_virtual_fee_amount'] = 'O valor da taxa precisa ser maior que zero, como 4,90.';
$_['error_token']           = 'Informe o token gerado no painel Frenet para habilitar o frete.';
