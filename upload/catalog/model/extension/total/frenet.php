<?php
use Frenet\Config as FrenetConfig;
use Frenet\GeoZone;
use Frenet\VirtualFee;

class ModelExtensionTotalFrenet extends Model {
	/**
	 * Adds the virtual products fee to a cart without shipped products, which the core sends to checkout without the shipping step.
	 *
	 * @param array<string, mixed> $total
	 * @return void
	 */
	public function getTotal(array $total): void {
		if ($this->cart->hasShipping()) {
			return;
		}

		$config = new FrenetConfig($this->config);

		if (!$config->isEnabled()) {
			return;
		}

		$virtual_fee = new VirtualFee($config);
		$fee = $virtual_fee->getAmount($this->cart->getProducts());

		if ($fee <= 0 || !$this->inGeoZone($config->getGeoZoneId()) || $this->hasFreeShippingCoupon()) {
			return;
		}

		$this->load->language('extension/total/frenet');

		$total['totals'][] = [
			'code'       => 'frenet',
			'title'      => $virtual_fee->getName($this->language->get('text_frenet_virtual_fee')),
			'value'      => $fee,
			'sort_order' => $this->config->get('total_frenet_sort_order')
		];

		foreach ($this->tax->getRates($fee, $config->getTaxClassId()) as $tax_rate) {
			$total['taxes'][$tax_rate['tax_rate_id']] = ($total['taxes'][$tax_rate['tax_rate_id']] ?? 0) + $tax_rate['amount'];
		}

		$total['total'] += $fee;
	}

	/**
	 * Whether the coupon of the session gives free shipping, which waives the fee as it waives the Frenet rate that carries it.
	 *
	 * @return bool
	 */
	private function hasFreeShippingCoupon(): bool {
		if (empty($this->session->data['coupon']) || !$this->config->get('total_coupon_status')) {
			return false;
		}

		$this->load->model('extension/total/coupon');

		return !empty($this->model_extension_total_coupon->getCoupon($this->session->data['coupon'])['shipping']);
	}

	/**
	 * Whether the payment address, or the store address before the buyer gives one, is in the Frenet geo zone.
	 *
	 * @param int $geo_zone_id
	 * @return bool
	 */
	private function inGeoZone(int $geo_zone_id): bool {
		if (!$geo_zone_id) {
			return true;
		}

		$address = $this->session->data['payment_address'] ?? [
			'country_id' => $this->config->get('config_country_id'),
			'zone_id'    => $this->config->get('config_zone_id')
		];

		return (new GeoZone($this->db, DB_PREFIX))->contains($geo_zone_id, (array)$address);
	}
}
