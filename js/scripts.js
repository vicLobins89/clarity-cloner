(() => {
	/**
	 * Triggers XHR requests to admin-ajax.php
	 * @param {array} data meta box data to send
	 * @param {string} action the ajax action triggered
	 * @param {Node} element optional metabox element to populate
	 */
	const triggerXHR = (data, action = 'cty-cloner', element = null) => {
		const xhttp = new XMLHttpRequest();
		xhttp.onreadystatechange = function () {
			if (this.readyState == 4) {
				const response = JSON.parse(xhttp.response);
				if (response.success && response.data) {
					for (let i = 0; i < response.data.length; i++) {
						const targetBlogId = response.data[i]['target-blog'];
						const metaboxHtml = response.data[i].html;
						const metabox = document.querySelector(`.cty-meta-box[data-target-blog="${targetBlogId}"]`);
						if (metabox && metaboxHtml) {
							const metaboxParent = metabox.parentNode;
							metaboxParent.removeChild(metabox);
							metaboxParent.innerHTML = metaboxHtml + metaboxParent.innerHTML;
							initClonerLoad();
						}
					}
				} else if (response.success && response.posts && element) {
					element.innerHTML = response.posts;
				} else {
					console.log(response);
				}
			}
		};

		xhttp.open('GET', `${cty_cloner.ajaxurl}?action=${action}&nonce=${cty_cloner.nonce}&data=${JSON.stringify(data)}`);
		xhttp.send();
	};

	/**
	 * On page load get the meta boxes and trigger any early load clikc events
	 * Like linking to existing post, which happens before we update the post
	 */
	const initClonerLoad = () => {
		const ctyMetaboxes = document.querySelectorAll('.cty-meta-box');
		ctyMetaboxes.forEach(element => {
			const data = {
				sourceBlog: element.dataset.sourceBlog,
				sourcePost: element.dataset.sourcePost,
				targetBlog: element.dataset.targetBlog
			};

			const radioInputs = element.querySelectorAll(`input[name="cloner_action[${data.targetBlog}]"]`);
			const ctySearchBox = element.querySelector('.cty-search');
			if (ctySearchBox) {
				const ctySearchInner = element.querySelector('.cty-search__inner');
				const ctySearch = element.querySelector('input[name="cty_link_search"]');

				radioInputs.forEach(input => {
					input.addEventListener('change', () => {
						if (input.value === 'link') {
							ctySearchBox.style.display = 'block';
						} else {
							ctySearchBox.style.display = 'none';
							ctySearchInner.innerHTML = '';
						}
					});
				});

				let timeout = null;
				ctySearch.addEventListener('input', () => {
					data.searchTerm = ctySearch.value;
					clearTimeout(timeout);
					timeout = setTimeout(() => {
						triggerXHR(data, 'cty-cloner-search', ctySearchInner);
					}, 200);
				});
			}
		});
	};

	// Trigger on full load.
	window.onload = () => {
		initClonerLoad();
	};

	/**
	 * Get meta boxes and initiate save process via XHR
	 */
	const initClonerSave = () => {
		// First collect saved meta box data.
		const clonerData = [];
		const ctyMetaboxes = document.querySelectorAll('.cty-meta-box');
		ctyMetaboxes.forEach(element => {
			const sourceBlog = element.dataset.sourceBlog;
			const sourcePost = element.dataset.sourcePost;
			const targetBlog = element.dataset.targetBlog;
			let targetPostField = element.querySelector(`input[name="cloner_target_post[${targetBlog}]"]`);
			if (targetPostField && targetPostField.type === 'radio') {
				targetPostField = element.querySelector(`input[name="cloner_target_post[${targetBlog}]"]:checked`);
			}
			const targetPost = targetPostField ? targetPostField.value : false;
			const actionField = element.querySelector(`input[name="cloner_action[${targetBlog}]"]:checked`);
			const clonerAction = actionField ? actionField.value : 'ignore';
			const metaboxData = {
				"cloner-action": clonerAction,
				"source-blog": sourceBlog,
				"source-post": sourcePost,
				"target-blog": targetBlog,
				"target-post": targetPost,
			};
			clonerData.push(metaboxData);
		});

		// Then process XHR.
		triggerXHR(clonerData);
	};

	// Trigger on post update or publish only once.
	let wasSavingPost = wp.data.select('core/editor').isSavingPost();
	let wasAutosavingPost = wp.data.select('core/editor').isAutosavingPost();
	let wasPreviewingPost = wp.data.select('core/editor').isPreviewingPost();
	wp.data.subscribe(() => {
		const isSavingPost = wp.data.select('core/editor').isSavingPost();
		const isAutosavingPost = wp.data.select('core/editor').isAutosavingPost();
		const isPreviewingPost = wp.data.select('core/editor').isPreviewingPost();

		// Trigger on save completion, except for autosaves that are not a post preview.
		const shouldTriggerSave = (
			(wasSavingPost && !isSavingPost && !wasAutosavingPost) ||
			(wasAutosavingPost && wasPreviewingPost && !isPreviewingPost)
		);

		// Save current state for next inspection.
		wasSavingPost = isSavingPost;
		wasAutosavingPost = isAutosavingPost;
		wasPreviewingPost = isPreviewingPost;

		if (shouldTriggerSave) {
			initClonerSave();
		}
	});
})();