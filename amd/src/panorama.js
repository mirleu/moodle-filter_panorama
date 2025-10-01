define(function () {
    return {
        init: async function (
            panoramaMoodleUserHint,
            serverUrl,
            cdnUrl,
            identifierKey,
            courseId,
            visualizerVersion,
            integrityHash,
        ) {
            if (serverUrl && identifierKey && identifierKey.length > 0) {
                if (!window.panoramaFetched) {
                    window.panoramaMoodleUserHint = panoramaMoodleUserHint;
                    window.panoramaFetched = true;
                    window.PANORAMA_SERVER_URL = serverUrl;
                    window.panoramaIdentifierKey = identifierKey;
                    window.PANORAMA_CDN_URL = cdnUrl;
                    window.courseId = courseId;
                    window.panoramaVisualizerVersion = visualizerVersion;
                    window.panoramaIntegrityHash = integrityHash;

                    function loadScript(url, integrity) {
                        const script = document.createElement('script');
                        script.src = url;
                        if (integrity) {
                            script.integrity = integrity;
                            script.crossOrigin = 'anonymous';
                        }
                        document.head.appendChild(script);
                    }

                    async function loadLatestMoodleVisualizer() {
                        const response = await fetch(
                            `${serverUrl}/panorama-visualizer/moodle`,
                        );
                        const scriptUrl = await response.text();
                        loadScript(scriptUrl);
                    }

                    async function loadVersionedMoodleVisualizer() {
                        loadScript(
                            `${cdnUrl}/resources/build/moodle-visualizer.${visualizerVersion}.js`,
                            integrityHash,
                        );
                    }

                    function attemptInit() {
                        if (window.panoramaInit) {
                            window.panoramaInit();
                            window.panoramaInit = undefined;
                        } else {
                            setTimeout(() => {
                                attemptInit();
                            }, 50);
                        }
                    }

                    if (visualizerVersion && visualizerVersion !== '') {
                        await loadVersionedMoodleVisualizer();
                    } else {
                        await loadLatestMoodleVisualizer();
                    }

                    attemptInit();
                }
            }
        },
    };
});
