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

                    visualizerVersion = '1762298392807';
                    integrityHash =
                        'sha512-GxjWm5iZ6QVwcGhtjtGSRiyLTcf0EbNCjuyxdBPmlFDV7iE8V9Oz8iVD7YcWGkJwABOfaJwhPvy+Nn+k3Cwx0w==';

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
