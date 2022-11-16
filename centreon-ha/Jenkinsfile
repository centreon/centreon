/*
** Variables.
*/
def serie = '22.04'
def maintenanceBranch = "${serie}.x"
def qaBranch = "dev-${serie}.x"

if (env.BRANCH_NAME.startsWith('release-')) {
  env.BUILD = 'RELEASE'
  env.REPO = 'testing'
} else if ((env.BRANCH_NAME == 'master') || (env.BRANCH_NAME == maintenanceBranch)) {
  env.BUILD = 'REFERENCE'
} else if ((env.BRANCH_NAME == 'develop') || (env.BRANCH_NAME == qaBranch)) {
  env.BUILD = 'QA'
  env.REPO = 'unstable'
} else {
  env.BUILD = 'CI'
}

// Skip sonarQ analysis on branch without PR  - Unable to merge
def securityAnalysisRequired = 'yes'
if (!env.CHANGE_ID && env.BUILD == 'CI') {
    securityAnalysisRequired = 'no'
}

/*
** Pipeline code.
*/
stage('Deliver and analyse sources') {
  node {
    sh 'setup_centreon_build.sh'
    dir('centreon-ha') {
      checkout scm
    }
    sh "./centreon-build/jobs/ha/${serie}/ha-source.sh"
    source = readProperties file: 'source.properties'
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
    publishHTML([
      allowMissing: false,
      keepAll: true,
      reportDir: 'summary',
      reportFiles: 'index.html',
      reportName: 'Centreon HA Build Artifacts',
      reportTitles: ''
    ])

    if (securityAnalysisRequired == 'yes') {
      // Run sonarQube analysis
      withSonarQubeEnv('SonarQubeDev') {
        sh "./centreon-build/jobs/ha/${serie}/ha-analysis.sh"
      }
      timeout(time: 10, unit: 'MINUTES') {
        def qualityGate = waitForQualityGate()
        if (qualityGate.status != 'OK') {
          currentBuild.result = 'FAIL'
        }
      }
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Source stage failure.');
    }
  }
}

stage('RPM/DEB packaging') {
  parallel 'centos7': {
    node {
      sh 'setup_centreon_build.sh'
      sh "./centreon-build/jobs/ha/${serie}/ha-package.sh centos7"
      stash name: 'rpms-centos7', includes: "output/noarch/*.rpm"
      archiveArtifacts artifacts: 'rpms-centos7.tar.gz'
      sh 'rm -rf output'
    }
  },
  'alma8': {
    node {
      sh 'setup_centreon_build.sh'
      sh "./centreon-build/jobs/ha/${serie}/ha-package.sh alma8"
      stash name: 'rpms-alma8', includes: "output/noarch/*.rpm"
      archiveArtifacts artifacts: 'rpms-alma8.tar.gz'
      sh 'rm -rf output'
    }
  },
  'Debian bullseye packaging and signing': {
      node {
        dir('centreon-ha') {
          checkout scm
        }
        sh 'docker run -i --entrypoint "/src/centreon-ha/ci/scripts/centreon-ha-package.sh" -w "/src" -v "$PWD:/src" -e "DISTRIB=bullseye" -e "VERSION=$VERSION" -e "RELEASE=$RELEASE" registry.centreon.com/centreon-debian11-dependencies:22.04'
        stash name: 'Debian11', includes: '*.deb'
        archiveArtifacts artifacts: "*"
      }
  }
  if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
    error('Package stage failure.')
  }
}

if ((env.BUILD == 'RELEASE') || (env.BUILD == 'CI') || (env.BUILD == 'QA') ) {
  stage('Delivery') {
    node {
      sh 'setup_centreon_build.sh'
      unstash 'rpms-centos7'
      unstash 'rpms-alma8'
      sh "./centreon-build/jobs/ha/${serie}/ha-delivery.sh"
      withCredentials([usernamePassword(credentialsId: 'nexus-credentials', passwordVariable: 'NEXUS_PASSWORD', usernameVariable: 'NEXUS_USERNAME')]) {
        checkout scm
        unstash "Debian11"
        sh '''for i in $(echo *.deb)
              do 
                curl -u $NEXUS_USERNAME:$NEXUS_PASSWORD -H "Content-Type: multipart/form-data" --data-binary "@./$i" https://apt.centreon.com/repository/22.04-$REPO/
              done
           '''    
      }
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Delivery stage failure.');
    }
  }
}
