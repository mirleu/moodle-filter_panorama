define(function () {
    return {
        init: async function (
            panoramaMoodleUserHint,
            serverUrl,
            cdnUrl,
            identifierKey,
            courseId,
        ) {
            if (serverUrl && identifierKey && identifierKey.length > 0) {
                if (!window.panoramaFetched) {
                    window.panoramaMoodleUserHint = panoramaMoodleUserHint;
                    window.panoramaFetched = true;
                    window.PANORAMA_SERVER_URL = serverUrl;
                    window.panoramaIdentifierKey = identifierKey;
                    window.PANORAMA_CDN_URL = cdnUrl;
                    window.courseId = courseId;

                    visualizerVersion = '1768866302050';
                    integrityHash =
                        'sha512-rmst2oESS/dJKqHkbEzLwZLjk7NH+dCFaIt4O8b4RkzdyyvZUQ8ftYYun2YJhIwN8aE5A8KNtpFnhJ2TAZueqA==';

                    window.panoramaVisualizerVersion = visualizerVersion;
                    window.panoramaIntegrityHash = integrityHash;

                    const script = document.createElement('script');
                    script.src = `${cdnUrl}/resources/build/moodle-visualizer.${visualizerVersion}.js`;
                    script.integrity = integrityHash;
                    script.crossOrigin = 'anonymous';
                    document.head.appendChild(script);

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

                    attemptInit();
                }
            }
        },
    };
});
