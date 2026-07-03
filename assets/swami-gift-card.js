(function () {
	function initGiftCard(root) {
		var serviceInputs = root.querySelectorAll('input[name="swami_service_key"]');
		var serviceName = root.querySelector('[data-swami-service-name]');
		var serviceMinutes = root.querySelector('[data-swami-service-minutes]');
		var servicePrice = root.querySelector('[data-swami-service-price]');
		var cardService = root.querySelector('[data-card-service]');
		var recipientInput = root.querySelector('[data-preview-recipient]');
		var senderInput = root.querySelector('[data-preview-sender]');
		var messageInput = root.querySelector('[data-preview-message]');
		var count = root.querySelector('[data-message-count]');
		var cardRecipient = root.querySelector('[data-card-recipient]');
		var cardSender = root.querySelector('[data-card-sender]');
		var cardMessage = root.querySelector('[data-card-message]');

		function updateService(input) {
			serviceInputs.forEach(function (item) {
				item.closest('.swami-gift-card__service').classList.toggle('is-selected', item === input);
			});
			serviceName.value = input.dataset.name || '';
			serviceMinutes.value = input.dataset.minutes || '';
			servicePrice.value = input.dataset.price || '';
			cardService.textContent = input.dataset.name || 'Masaje Tailandés';
		}

		function updatePreview() {
			var recipient = recipientInput.value.trim();
			var sender = senderInput.value.trim();
			var message = messageInput.value.trim();
			cardRecipient.textContent = recipient || '______________________';
			cardSender.textContent = sender || '______________________';
			cardMessage.textContent = message;
			count.textContent = String(messageInput.value.length);
		}

		serviceInputs.forEach(function (input) {
			input.addEventListener('change', function () {
				updateService(input);
			});
		});

		root.querySelectorAll('[data-message-template]').forEach(function (button) {
			button.addEventListener('click', function () {
				messageInput.value = button.dataset.messageTemplate || '';
				messageInput.focus();
				updatePreview();
			});
		});

		[recipientInput, senderInput, messageInput].forEach(function (input) {
			input.addEventListener('input', updatePreview);
		});

		updatePreview();
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.swami-gift-card').forEach(initGiftCard);
	});
})();
