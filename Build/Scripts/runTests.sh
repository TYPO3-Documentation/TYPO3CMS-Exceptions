#!/usr/bin/env bash

#
# TYPO3 core test runner based on docker.
#

cleanUp() {
    ATTACHED_CONTAINERS=$(${CONTAINER_BIN} ps --filter network=${NETWORK} --format='{{.Names}}')
    for ATTACHED_CONTAINER in ${ATTACHED_CONTAINERS}; do
        ${CONTAINER_BIN} rm -f ${ATTACHED_CONTAINER} >/dev/null
    done
    ${CONTAINER_BIN} network rm ${NETWORK} >/dev/null
}

loadHelp() {
    # Load help text into $HELP
    read -r -d '' HELP <<EOF
TYPO3 core test runner. Execute acceptance, unit, functional and other test suites in
a container based test environment. Handles execution of single test files, sending
xdebug information to a local IDE and more.

Usage: $0 [options] [file]

Options:
    -s <...>
        Specifies the test suite to run
            - composerInstall: Install composer dependencies for build application.
            - createMissingExceptionCodeFiles: This creates documentation pages for missing exception codes based on a template.
            - updateExceptionCodesAllTags: Fetch and create exception codes collection for all tags (rebuild).
            - updateExceptionCodesMissingTags: Fetch all tags with missing exception codes collection and create them.

    -c
        Create a commit if files have been changed or created with a generic commit message describing what have
        changed.
        Note: Meant to be used for automatic GitHub Action workflows.

        Only with -s createMissingExceptionCodeFiles|updateExceptionCodesAllTags|updateExceptionCodesMissingTags

    -p <8.1|8.2|8.3>
        Specifies the PHP minor version to be used
            - 8.1 (default): use PHP 8.1
            - 8.2: use PHP 8.2
            - 8.3: use PHP 8.3

    -x
        Only with -s createMissingExceptionCodeFiles|updateExceptionCodesAllTags|updateExceptionCodesMissingTags
        Send information to host instance for test or system under test break points. This is especially
        useful if a local PhpStorm instance is listening on default xdebug port 9003. A different port
        can be selected with -y

    -y <port>
        Send xdebug information to a different port than default 9003 if an IDE like PhpStorm
        is not listening on default port.

    -u
        Update existing typo3/core-testing-*:latest container images and remove dangling local volumes.
        New images are published once in a while and only the latest ones are supported by core testing.
        Use this if weird test errors occur. Also removes obsolete image versions of typo3/core-testing-*.

    -h
        Show this help.

Examples:
    # Create exception code pages for missing exceptions
    ./Build/Scripts/runTests.sh -s createMissingExceptionCodeFiles

    # Fetch all TYPO3 tags and update exception code collections (rebuild).
    ./Build/Scripts/runTests.sh -s updateExceptionCodesAllTags

    # Fetch all TYPO3 tags which has no exception code collection yet and create it.
    ./Build/Scripts/runTests.sh -s updateExceptionCodesMissingTags
EOF
}

# Test if docker exists, else exit out with error
if ! type "docker" >/dev/null; then
    echo "This script relies on docker. Please install" >&2
    exit 1
fi

# Option defaults
TEST_SUITE="help"
PHP_VERSION="8.1"
PHP_XDEBUG_ON=0
PHP_XDEBUG_PORT=9003
CONTAINER_BIN="docker"
CREATE_COMMIT=0

# Option parsing updates above default vars
# Reset in case getopts has been used previously in the shell
OPTIND=1
# Array for invalid options
INVALID_OPTIONS=()
# Simple option parsing based on getopts (! not getopt)
while getopts "chp:s:uxy:" OPT; do
    case ${OPT} in
        s)
            TEST_SUITE=${OPTARG}
            ;;
        c)
            CREATE_COMMIT=1
            ;;
        p)
            PHP_VERSION=${OPTARG}
            if ! [[ ${PHP_VERSION} =~ ^(8.1|8.2|8.3)$ ]]; then
                INVALID_OPTIONS+=("${OPTARG}")
            fi
            ;;
        x)
            PHP_XDEBUG_ON=1
            ;;
        y)
            PHP_XDEBUG_PORT=${OPTARG}
            ;;
        h)
            TEST_SUITE=help
            ;;
        u)
            TEST_SUITE=update
            ;;
        \?)
            INVALID_OPTIONS+=("${OPTARG}")
            ;;
        :)
            INVALID_OPTIONS+=("${OPTARG}")
            ;;
    esac
done
shift $((OPTIND - 1))

# Exit on invalid options
if [ ${#INVALID_OPTIONS[@]} -ne 0 ]; then
    echo "Invalid option(s):" >&2
    for I in "${INVALID_OPTIONS[@]}"; do
        echo "-"${I} >&2
    done
    echo >&2
    echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
    exit 1
fi

COMPOSER_ROOT_VERSION="13.0.x-dev"
HOST_UID=$(id -u)
HOST_PID=$(id -g)
USERSET=""
if [ $(uname) != "Darwin" ]; then
    USERSET="--user $HOST_UID"
fi

# Go to the directory this script is located, so everything else is relative
# to this dir, no matter from where this script is called, then go up two dirs.
THIS_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null && pwd)"
cd "$THIS_SCRIPT_DIR" || exit 1
cd ../../ || exit 1
CORE_ROOT="${PWD}"

# Create filesystem structure:
mkdir -p .Build

IMAGE_PREFIX="docker.io/"
# Non-CI fetches TYPO3 images (php and nodejs) from ghcr.io
TYPO3_IMAGE_PREFIX="ghcr.io/"
CONTAINER_INTERACTIVE="-it --init"

IS_CORE_CI=0
# ENV var "CI" is set by gitlab-ci. We use it here to distinct 'local' and 'CI' environment.
if [ "${CI}" == "true" ]; then
    IS_CORE_CI=1
    # Remove interactive tty for CI runs
    CONTAINER_INTERACTIVE=""
fi

IMAGE_PHP="${TYPO3_IMAGE_PREFIX}typo3/core-testing-$(echo "php${PHP_VERSION}" | sed -e 's/\.//'):latest"
SUFFIX=$(echo $RANDOM)
NETWORK="typo3-docs-${SUFFIX}"
${CONTAINER_BIN} network create ${NETWORK} >/dev/null

CONTAINER_COMMON_PARAMS="${CONTAINER_INTERACTIVE} --rm --network $NETWORK --add-host "host.docker.internal:host-gateway" $USERSET -v ${CORE_ROOT}:${CORE_ROOT} -w ${CORE_ROOT}"

if [ ${PHP_XDEBUG_ON} -eq 0 ]; then
    XDEBUG_MODE="-e XDEBUG_MODE=off"
    XDEBUG_CONFIG=" "
else
    XDEBUG_MODE="-e XDEBUG_MODE=debug -e XDEBUG_TRIGGER=foo"
    XDEBUG_CONFIG="client_port=${PHP_XDEBUG_PORT} client_host=host.docker.internal"
fi

# Suite execution
case ${TEST_SUITE} in
    composerInstall)
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-install-${SUFFIX} -e COMPOSER_CACHE_DIR=.Build/.cache/composer ${IMAGE_PHP} composer install --no-progress --no-interaction --working-dir=${CORE_ROOT}/Build/app
        SUITE_EXIT_CODE=$?
        ;;
    createMissingExceptionCodeFiles)
        AUTO_COMMIT_OPTION=""
        [[ "${CREATE_COMMIT}" -eq 1 ]] ; AUTO_COMMIT_OPTION="--auto-commit"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name exceptioncodes-missing-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_PHP} Build/app/console.php exception-codes:generate-pages --exception-collections-path="${CORE_ROOT}/Build/Exceptions" ${AUTO_COMMIT_OPTION}
        SUITE_EXIT_CODE=$?
        ;;
    updateExceptionCodesAllTags)
        AUTO_COMMIT_OPTION=""
        [[ "${CREATE_COMMIT}" -eq 1 ]] ; AUTO_COMMIT_OPTION="--auto-commit"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name exceptioncodes-missing-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_PHP} Build/app/console.php exception-codes:fetch --exception-collections-path="${CORE_ROOT}/Build/Exceptions" --repo-path="${CORE_ROOT}/.Build/typo3-core-repo" --repo-clone-url="https://github.com/TYPO3/typo3.git" ${AUTO_COMMIT_OPTION} all
        SUITE_EXIT_CODE=$?
        ;;
    updateExceptionCodesMissingTags)
        AUTO_COMMIT_OPTION=""
        [[ "${CREATE_COMMIT}" -eq 1 ]] ; AUTO_COMMIT_OPTION="--auto-commit"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name exceptioncodes-missing-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_PHP} Build/app/console.php exception-codes:fetch --exception-collections-path="${CORE_ROOT}/Build/Exceptions" --repo-path="${CORE_ROOT}/.Build/typo3-core-repo" --repo-clone-url="https://github.com/TYPO3/typo3.git" ${AUTO_COMMIT_OPTION} missing
        SUITE_EXIT_CODE=$?
        ;;
    help)
        loadHelp
        echo "${HELP}"
        exit 0
        ;;
    update)
        # prune unused, dangling local volumes
        echo "> prune unused, dangling local volumes"
        ${CONTAINER_BIN} volume ls -q -f driver=local -f dangling=true | awk '$0 ~ /^[0-9a-f]{64}$/ { print }' | xargs -I {} ${CONTAINER_BIN} volume rm {}
        echo ""
        # pull typo3/core-testing-*:latest versions of those ones that exist locally
        echo "> pull ${TYPO3_IMAGE_PREFIX}typo3/core-testing-*:latest versions of those ones that exist locally"
        ${CONTAINER_BIN} images ${TYPO3_IMAGE_PREFIX}typo3/core-testing-*:latest --format "{{.Repository}}:latest" | xargs -I {} ${CONTAINER_BIN} pull {}
        echo ""
        # remove "dangling" typo3/core-testing-* images (those tagged as <none>)
        echo "> remove \"dangling\" ${TYPO3_IMAGE_PREFIX}typo3/core-testing-* images (those tagged as <none>)"
        ${CONTAINER_BIN} images ${TYPO3_IMAGE_PREFIX}typo3/core-testing-* --filter "dangling=true" --format "{{.ID}}" | xargs -I {} ${CONTAINER_BIN} rmi -f {}
        echo ""
        ;;
    *)
        loadHelp
        echo "Invalid -s option argument ${TEST_SUITE}" >&2
        echo >&2
        echo "${HELP}" >&2
        exit 1
        ;;
esac

cleanUp

# Print summary
echo "" >&2
echo "###########################################################################" >&2
echo "Result of ${TEST_SUITE}" >&2
if [[ ${IS_CORE_CI} -eq 1 ]]; then
    echo "Environment: CI" >&2
else
    echo "Environment: local" >&2
fi
echo "PHP: ${PHP_VERSION}" >&2
if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
    echo "SUCCESS" >&2
else
    echo "FAILURE" >&2
fi
echo "###########################################################################" >&2
echo "" >&2

# Exit with code of test suite - This script return non-zero if the executed test failed.
exit $SUITE_EXIT_CODE
