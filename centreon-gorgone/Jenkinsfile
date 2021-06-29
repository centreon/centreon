import groovy.json.JsonSlurper
/*
** Variables.
*/
def serie = '21.10'
def maintenanceBranch = "${serie}.x"
if (env.BRANCH_NAME.startsWith('release-')) {
  env.BUILD = 'RELEASE'
} else if ((env.BRANCH_NAME == 'master') || (env.BRANCH_NAME == maintenanceBranch)) {
  env.BUILD = 'REFERENCE'
} else {
  env.BUILD = 'CI'
}

/*
** Pipeline code.
*/
stage('Sonar analysis') {
  node {
    sh 'setup_centreon_build.sh'
    dir('centreon-gorgone') {
      checkout scm
    }
    sh "./centreon-build/jobs/gorgone/${serie}/gorgone-source.sh"
    source = readProperties file: 'source.properties'
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
    // Run sonarQube analysis
    withSonarQubeEnv('SonarQubeDev') {
      sh "./centreon-build/jobs/gorgone/${serie}/gorgone-analysis.sh"
    }
    def reportFilePath = "target/sonar/report-task.txt"
    def reportTaskFileExists = fileExists "${reportFilePath}"
      if (reportTaskFileExists) {
        echo "Found report task file"
        def taskProps = readProperties file: "${reportFilePath}"
        echo "taskId[${taskProps['ceTaskId']}]"
        timeout(time: 10, unit: 'MINUTES') {
          while (true) {
              sleep 5
              def taskStatusResult    =
                  sh(returnStdout: true,
                     script: "curl -s -X GET -u ${authString} \'${sonarProps['sonar.host.url']}/api/ce/task?id=${taskProps['ceTaskId']}\'")
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
  }
}

try {
  stage('Package') {
    parallel 'centos7': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/gorgone/${serie}/gorgone-package.sh centos7"
        archiveArtifacts artifacts: 'rpms-centos7.tar.gz'
      }
    },
    'centos8': {
      node {
        sh 'setup_centreon_build.sh'
        sh "./centreon-build/jobs/gorgone/${serie}/gorgone-package.sh centos8"
        archiveArtifacts artifacts: 'rpms-centos8.tar.gz'
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
        sh "./centreon-build/jobs/gorgone/${serie}/gorgone-delivery.sh"
      }
      if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
        error('Delivery stage failure.');
      }
    }
  }
} catch(e) {
  currentBuild.result = 'FAILURE'
}
