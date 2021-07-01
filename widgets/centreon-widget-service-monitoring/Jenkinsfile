import groovy.json.JsonSlurper

/*
** Variables.
*/
properties([buildDiscarder(logRotator(numToKeepStr: '50'))])
def serie = '21.10'
def maintenanceBranch = "${serie}.x"

if (env.BRANCH_NAME.startsWith('release-')) {
  env.BUILD = 'RELEASE'
} else if ((env.BRANCH_NAME == 'master') || (env.BRANCH_NAME == maintenanceBranch)) {
  env.BUILD = 'REFERENCE'
} else {
  env.BUILD = 'CI'
}
def featureFiles = []

/*
** Pipeline code.
*/
stage('Source') {
  node {
    sh 'setup_centreon_build.sh'
    env.WIDGET = 'service-monitoring'
    dir("centreon-widget-${env.WIDGET}") {
      checkout scm
    }
    sh "./centreon-build/jobs/widgets/${serie}/widget-source.sh"
    source = readProperties file: 'source.properties'
    env.PROJECT = "${source.PROJECT}"
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
    env.SUMMARY = "${source.SUMMARY}"
    publishHTML([
      allowMissing: false,
      keepAll: true,
      reportDir: 'summary',
      reportFiles: 'index.html',
      reportName: 'Centreon Build Artifacts',
      reportTitles: ''
    ])
  }
}

try {
  stage('Unit tests') {
    parallel 'centos7': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/widgets/${serie}/widget-unittest.sh centos7"
        if (currentBuild.result == 'UNSTABLE')
          currentBuild.result = 'FAILURE'

        if (env.CHANGE_ID) { // pull request to comment with coding style issues
          ViolationsToGitHub([
            repositoryName: 'centreon-widget-service-monitoring',
            pullRequestId: env.CHANGE_ID,

            createSingleFileComments: true,
            commentOnlyChangedContent: true,
            commentOnlyChangedFiles: true,
            keepOldComments: false,

            commentTemplate: "**{{violation.severity}}**: {{violation.message}}",

            violationConfigs: [
              [parser: 'CHECKSTYLE', pattern: '.*/codestyle-be.xml$', reporter: 'Checkstyle']
            ]
          ])
        }

        discoverGitReferenceBuild()
        recordIssues(
          enabledForFailure: true,
          failOnError: true,
          qualityGates: [[threshold: 1, type: 'DELTA', unstable: false]],
          tool: phpCodeSniffer(id: 'phpcs', name: 'phpcs', pattern: 'codestyle-be.xml'),
          trendChartType: 'NONE'
        )

        // Run sonarQube analysis
        withSonarQubeEnv('SonarQubeDev') {
          sh "./centreon-build/jobs/widgets/${serie}/widget-analysis.sh"
        }
      }
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Unit tests stage failure.');
    }
  }

  // sonarQube step to get qualityGate result
  stage('Quality gate') {
    node {
      def reportFilePath = "target/sonar/report-task.txt"
      def reportTaskFileExists = fileExists "${reportFilePath}"
      if (reportTaskFileExists) {
        echo "Found report task file"
        def taskProps = readProperties file: "${reportFilePath}"
        echo "taskId[${taskProps['ceTaskId']}]"
        timeout(time: 10, unit: 'MINUTES') {
          while (true) {
            sleep 10
            def taskStatusResult    =
            sh(returnStdout: true, script: "curl -s -X GET -u ${authString} \'${sonarProps['sonar.host.url']}/api/ce/task?id=${taskProps['ceTaskId']}\'")
            echo "taskStatusResult[${taskStatusResult}]"
            def taskStatus  = new JsonSlurper().parseText(taskStatusResult).task.status
            echo "taskStatus[${taskStatus}]"
            // Status can be SUCCESS, ERROR, PENDING, or IN_PROGRESS. The last two indicate it's
            // not done yet.
            if (taskStatus != "IN_PROGRESS" && taskStatus != "PENDING") {
              break;
            }
            def qualityGate = waitForQualityGate()
            if (qualityGate.status != 'OK') {
              currentBuild.result = 'FAIL'
            }
          }
        }
      }
      if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
        error("Quality gate failure: ${qualityGate.status}.");
      }
    }
  }

  stage('Package') {
    parallel 'centos7': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/widgets/${serie}/widget-package.sh centos7"
      }
    },
    'centos8': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/widgets/${serie}/widget-package.sh centos8"
      }
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Package stage failure.');
    }
  }

  if ((env.BUILD == 'RELEASE') || (env.BUILD == 'REFERENCE')) {
    stage('Delivery') {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/widgets/${serie}/widget-delivery.sh"
      }
      if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
        error('Delivery stage failure.');
      }
    }
  }
} catch(e) {
  if ((env.BUILD == 'RELEASE') || (env.BUILD == 'REFERENCE')) {
    slackSend channel: "#monitoring-metrology",
        color: "#F30031",
        message: "*FAILURE*: `CENTREON WIDGET SERVICE MONITORING` <${env.BUILD_URL}|build #${env.BUILD_NUMBER}> on branch ${env.BRANCH_NAME}\n" +
            "*COMMIT*: <https://github.com/centreon/centreon-widget-service-monitoring/commit/${source.COMMIT}|here> by ${source.COMMITTER}\n" +
            "*INFO*: ${e}"
  }

  currentBuild.result = 'FAILURE'
}
