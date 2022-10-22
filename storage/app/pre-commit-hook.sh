#!/bin/sh

SCRIPT_DISABLED=false
SHOW_PASSED_FILES=false
RULESET=./phpcs.xml # File with the phpcs settings
STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep ".php\{0,1\}$")

if [ "$STAGED_FILES" = "" ] || $SCRIPT_DISABLED; then
  exit 0
fi

PASS=true
echo "Validating PHPCS (Code Sniffer):"

# Check for phpcs
which ./vendor/bin/phpcs &> /dev/null
if [[ "$?" == 1 ]]; then
  echo "\033[41mPlease install PHPCS before commit in this project:\033[0m"
  echo "Run: composer require squizlabs/php_codesniffer"
  exit 1
fi

for FILE in $STAGED_FILES
do
  ./vendor/bin/phpcs --standard="$RULESET" "$FILE"

  if [[ "$?" != 0 ]]; then
    echo "\033[41mPHPCS Failed: $FILE\033[0m"
    PASS=false
  elif $SHOW_PASSED_FILES; then
    echo "\033[32mPHPCS Passed: $FILE\033[0m"
  fi
done

echo '\nPHPCS validation finished.'

if ! $PASS; then
  echo "\033[41mCOMMIT FAILED:\033[0m Your commit contains files that should pass PHPCS but do not. Please fix the PHPCS errors above and try again."
  echo 'Tip: try to run the command "composer phpcbf"\n'
  exit 1
else
  echo "\n\033[42mPHPCS VALIDATION SUCCEEDED\033[0m\n"
fi

exit $?
