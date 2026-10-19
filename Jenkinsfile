// =====================================================================
// Pipeline CI/CD d'AquaSecure (Laravel 12, PHP 8.2)
// Même architecture que CorpFlow HR :
//   tests → SonarQube → Quality Gate → image Docker → livrable → test de fumée
//   → Nexus → déploiement Kubernetes (retour arrière automatique en cas d'échec)
// =====================================================================

/** Vrai si l'un des commits de ce build modifie un fichier du dossier donné. */
def dossierModifie(String dossier) {
    for (changeSet in currentBuild.changeSets) {
        for (entry in changeSet.items) {
            for (fichier in entry.affectedFiles) {
                if (fichier.path.startsWith(dossier)) {
                    return true
                }
            }
        }
    }
    return false
}

/** Vérifie si SonarQube répond. */
def sonarDisponible(String url) {
    return sh(script: "curl -sf ${url}/api/system/status | grep -q '\"status\":\"UP\"'",
              returnStatus: true) == 0
}

pipeline {
    agent any

    options {
        timestamps()
        buildDiscarder(logRotator(numToKeepStr: '10', artifactNumToKeepStr: '3'))
        disableConcurrentBuilds()
        timeout(time: 60, unit: 'MINUTES')
    }

    triggers {
        pollSCM('H/5 * * * *')
    }

    environment {
        REGISTRY        = '192.168.255.129:8082'
        IMAGE           = "${REGISTRY}/aquasecure"
        NAMESPACE       = 'aquasecure'
        SONAR_URL       = 'http://192.168.255.129:9000'
        TEST_IMAGE      = "aquasecure-tests:${env.BUILD_NUMBER}"
        TEST_NETWORK    = "aquasecure-tests-${env.BUILD_NUMBER}"
        MYSQL_CONTAINER = "aquasecure-mysql-${env.BUILD_NUMBER}"
        TEST_CONTAINER  = "aquasecure-tests-${env.BUILD_NUMBER}"
        SMOKE_CONTAINER = "aquasecure-fumee-${env.BUILD_NUMBER}"
    }

    stages {

        stage('Préparation') {
            steps {
                script {
                    // Version = numéro du build + commit Git : chaque image est traçable jusqu'au code
                    env.IMAGE_TAG = env.GIT_COMMIT ? "${env.BUILD_NUMBER}-${env.GIT_COMMIT.take(7)}" : "${env.BUILD_NUMBER}"
                    env.SONAR_UP  = sonarDisponible(env.SONAR_URL) ? 'true' : 'false'
                    env.K8S_MODIFIE = dossierModifie('k8s/') ? 'true' : 'false'
                    // Clé de chiffrement jetable, uniquement pour les tests et le test de fumée
                    env.APP_KEY_TEST = 'base64:' + sh(script: 'openssl rand -base64 32', returnStdout: true).trim()

                    echo "Version de l'image : ${env.IMAGE_TAG}"
                    echo(env.SONAR_UP == 'true' ? 'SonarQube disponible' : 'SonarQube arrêté : analyse ignorée')
                    echo "Manifestes Kubernetes modifiés : ${env.K8S_MODIFIE}"
                }
            }
        }

        stage('Tests (PHPUnit + MySQL)') {
            steps {
                // Image de tests (étape "test" du Dockerfile) : dépendances de dev + PCOV
                sh 'docker build --target test -t "$TEST_IMAGE" .'

                // Base MySQL jetable, comme phpunit.xml (base gestion_eau_test)
                sh '''
                    docker network create "$TEST_NETWORK"
                    docker run -d --name "$MYSQL_CONTAINER" --network "$TEST_NETWORK" \
                      -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=gestion_eau_test \
                      mysql:8.0

                    # Attente de MySQL (connexion TCP réelle, pas seulement le processus démarré)
                    for i in $(seq 1 60); do
                        if docker exec "$MYSQL_CONTAINER" mysql -h 127.0.0.1 -uroot -proot -e "SELECT 1" gestion_eau_test > /dev/null 2>&1; then
                            break
                        fi
                        sleep 2
                    done
                    docker exec "$MYSQL_CONTAINER" mysql -h 127.0.0.1 -uroot -proot -e "SELECT 1" gestion_eau_test > /dev/null
                '''

                sh '''
                    docker run --name "$TEST_CONTAINER" --network "$TEST_NETWORK" \
                      -e APP_KEY="$APP_KEY_TEST" \
                      -e DB_HOST="$MYSQL_CONTAINER" -e DB_USERNAME=root -e DB_PASSWORD=root \
                      "$TEST_IMAGE" \
                      php vendor/bin/phpunit --log-junit /tmp/junit.xml --coverage-clover /tmp/coverage.xml
                '''
            }
            post {
                always {
                    sh '''
                        docker cp "$TEST_CONTAINER:/tmp/junit.xml" junit.xml || true
                        docker cp "$TEST_CONTAINER:/tmp/coverage.xml" coverage.xml || true
                        docker rm -f "$TEST_CONTAINER" "$MYSQL_CONTAINER" || true
                        docker network rm "$TEST_NETWORK" || true
                        # Chemins du conteneur → chemins vus par SonarQube
                        sed -i 's#/var/www/html/#/usr/src/#g' coverage.xml || true
                    '''
                    junit allowEmptyResults: true, testResults: 'junit.xml'
                }
            }
        }

        stage('Analyse SonarQube') {
            when { environment name: 'SONAR_UP', value: 'true' }
            steps {
                catchError(buildResult: 'UNSTABLE', stageResult: 'FAILURE') {
                    withSonarQubeEnv('sonarqube') {
                        sh '''
                            docker run --rm \
                              --memory 2g \
                              -u "$(id -u):$(id -g)" \
                              -e SONAR_HOST_URL="$SONAR_HOST_URL" \
                              -e SONAR_TOKEN="$SONAR_AUTH_TOKEN" \
                              -e SONAR_USER_HOME=/usr/src/.sonar \
                              -e SONAR_SCANNER_OPTS="-Xmx512m" \
                              -v "$PWD:/usr/src" \
                              sonarsource/sonar-scanner-cli \
                              -Dsonar.working.directory=/usr/src/.scannerwork
                        '''
                    }
                    script { env.SONAR_ANALYSE_OK = 'true' }
                }
            }
        }

        stage('Quality Gate') {
            when { environment name: 'SONAR_ANALYSE_OK', value: 'true' }
            steps {
                catchError(buildResult: 'UNSTABLE', stageResult: 'FAILURE') {
                    timeout(time: 5, unit: 'MINUTES') {
                        waitForQualityGate abortPipeline: false
                    }
                }
            }
        }

        stage('Image Docker') {
            steps {
                sh 'docker build --target production -t "$IMAGE:$IMAGE_TAG" -t "$IMAGE:latest" .'
            }
        }

        stage('Archivage du livrable') {
            steps {
                // Le code livré (avec ses dépendances de production) est extrait de l'image puis archivé
                sh '''
                    rm -rf livrable && mkdir livrable
                    docker create --name "livrable-$BUILD_NUMBER" "$IMAGE:$IMAGE_TAG"
                    docker cp "livrable-$BUILD_NUMBER:/var/www/html/." livrable/
                    docker rm "livrable-$BUILD_NUMBER"
                    tar -czf "aquasecure-$IMAGE_TAG.tar.gz" -C livrable .
                    rm -rf livrable
                '''
                archiveArtifacts artifacts: "aquasecure-${env.IMAGE_TAG}.tar.gz", fingerprint: true
                                // L'archive est conservée par Jenkins : on la retire de l'espace de travail
                sh 'rm -f aquasecure-*.tar.gz'
            }
        }

        stage('Test de fumée') {
            steps {
                // L'image démarre-t-elle ? Apache répond-il ? Les pages s'affichent-elles ?
                // (sans base de données : sessions et cache en mémoire)
                sh '''
                    docker run -d --name "$SMOKE_CONTAINER" \
                      -e APP_KEY="$APP_KEY_TEST" -e APP_ENV=production -e APP_DEBUG=false \
                      -e SESSION_DRIVER=array -e CACHE_STORE=array -e LOG_CHANNEL=stderr \
                      "$IMAGE:$IMAGE_TAG"

                    for i in $(seq 1 30); do
                        if docker exec "$SMOKE_CONTAINER" curl -sf -o /dev/null http://127.0.0.1:8080/up; then
                            break
                        fi
                        sleep 1
                    done

                    docker exec "$SMOKE_CONTAINER" curl -sf -o /dev/null http://127.0.0.1:8080/up
                    docker exec "$SMOKE_CONTAINER" curl -sf -o /dev/null http://127.0.0.1:8080/connexion
                    docker exec "$SMOKE_CONTAINER" curl -sf -o /dev/null http://127.0.0.1:8080/favicon.svg
                    echo "Apache sert l'application : /up, /connexion et les fichiers statiques répondent"
                '''
            }
            post {
                failure {
                    sh 'docker logs "$SMOKE_CONTAINER" || true'
                }
                always {
                    sh 'docker rm -f "$SMOKE_CONTAINER" || true'
                }
            }
        }

        stage('Envoi vers Nexus') {
            steps {
                withCredentials([usernamePassword(credentialsId: 'nexus-docker',
                                                  usernameVariable: 'NEXUS_USER',
                                                  passwordVariable: 'NEXUS_PWD')]) {
                    sh '''
                        echo "$NEXUS_PWD" | docker login "$REGISTRY" -u "$NEXUS_USER" --password-stdin
                        docker push -q "$IMAGE:$IMAGE_TAG"
                        docker push -q "$IMAGE:latest"
                    '''
                }
            }
        }

        stage('Déploiement Kubernetes') {
            steps {
                withCredentials([file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG')]) {
                    sh '''
                        # Premier déploiement, ou manifestes modifiés : on (ré)applique le dossier k8s/
                        if [ "$K8S_MODIFIE" = "true" ] || ! kubectl -n "$NAMESPACE" get deployment aquasecure > /dev/null 2>&1; then
                            kubectl apply -f k8s/
                        fi

                        # Nouvelle version : migrations (conteneur d'initialisation), application et planificateur
                        kubectl -n "$NAMESPACE" set image deployment/aquasecure \
                          migrations="$IMAGE:$IMAGE_TAG" app="$IMAGE:$IMAGE_TAG"
                        kubectl -n "$NAMESPACE" set image deployment/aquasecure-planificateur \
                          planificateur="$IMAGE:$IMAGE_TAG"

                        if ! kubectl -n "$NAMESPACE" rollout status deployment/aquasecure --timeout=300s; then
                            echo "Échec du déploiement : retour à la version précédente"
                            kubectl -n "$NAMESPACE" rollout undo deployment/aquasecure
                            kubectl -n "$NAMESPACE" rollout undo deployment/aquasecure-planificateur
                            exit 1
                        fi
                        kubectl -n "$NAMESPACE" rollout status deployment/aquasecure-planificateur --timeout=180s
                    '''
                }
            }
        }
    }

    post {
        always {
            sh 'docker logout "$REGISTRY" || true'
            sh '''
                docker rmi "$IMAGE:$IMAGE_TAG" "$IMAGE:latest" "$TEST_IMAGE" 2>/dev/null || true
            '''
        }
        success {
            echo "AquaSecure livré en version ${env.IMAGE_TAG} : http://aquasecure.local:30080"
        }
    }
}