@block @block_socialcomments @javascript
Feature: Social Comments block on a category page is inherited by courses
  In order to allow course participants to comment without placing the block on every course
  As an admin
  I want to add the Social Comments block to a category page and have it appear in all courses within that category

  Background:
    Given the following "categories" exist:
      | name          | category | idnumber |
      | SC Category   | 0        | SCCAT1   |
      | SC Category 2 | 0        | SCCAT2   |
      | SC Category 3 | 0        | SCCAT3   |
    And the following "courses" exist:
      | fullname    | shortname | category |
      | SC Course 1 | SCC1      | SCCAT1   |
      | SC Course 2 | SCC2      | SCCAT2   |
      | SC Course 3 | SCC3      | SCCAT3   |
      | SC Course 4 | SCC4      | SCCAT1   |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | One      |
      | student2 | Student   | Two      |
      | student3 | Student   | Three    |
      | student4 | Student   | Four     |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | SCC1   | student |
      | student1 | SCC4   | student |
      | student4 | SCC4   | student |
      | student2 | SCC2   | student |
      | student3 | SCC3   | student |

  Scenario: Category page shows info message when block is added
    Given I log in as "admin"
    And I am on the "SCCAT1" "category" page
    And I turn editing mode on
    When I add the "Social Comments" block
    Then I should see "Content only available in course context." in the "Social Comments" "block"

  Scenario: Block shows in own category courses only
    Given I log in as "admin"
    And I am on the "SCCAT1" "category" page
    And I turn editing mode on
    And I add the "Social Comments" block
    And I configure the "Social Comments" block
    And I click on "Expand all" "link"
    And I set the following fields to these values:
      | Page contexts         | 1        |
      | Display on page types | Any page |
    And I press "Save changes"
    And I turn editing mode off
    When I am on "SCC1" course homepage
    Then "Social Comments" "block" should exist
    When I am on "SCC4" course homepage
    Then "Social Comments" "block" should exist
    When I am on "SCC2" course homepage
    Then "Social Comments" "block" should not exist
    When I am on "SCC3" course homepage
    Then "Social Comments" "block" should not exist

  Scenario: Each category has its own block independently
    Given I log in as "admin"
    And I am on the "SCCAT1" "category" page
    And I turn editing mode on
    And I add the "Social Comments" block
    And I configure the "Social Comments" block
    And I click on "Expand all" "link"
    And I set the following fields to these values:
      | Page contexts         | 1        |
      | Display on page types | Any page |
    And I press "Save changes"
    And I am on the "SCCAT2" "category" page
    And I add the "Social Comments" block
    And I configure the "Social Comments" block
    And I click on "Expand all" "link"
    And I set the following fields to these values:
      | Page contexts         | 1        |
      | Display on page types | Any page |
    And I press "Save changes"
    And I turn editing mode off
    When I am on the "SCCAT1" "category" page
    Then I should see "Content only available in course context." in the "Social Comments" "block"
    When I am on the "SCCAT2" "category" page
    Then I should see "Content only available in course context." in the "Social Comments" "block"
    When I am on "SCC1" course homepage
    Then "Social Comments" "block" should exist
    When I am on "SCC4" course homepage
    Then "Social Comments" "block" should exist
    When I am on "SCC2" course homepage
    Then "Social Comments" "block" should exist
    When I am on "SCC3" course homepage
    Then "Social Comments" "block" should not exist

  Scenario: Comments stay in their own course across categories
    Given I log in as "admin"
    And I am on the "SCCAT1" "category" page
    And I turn editing mode on
    And I add the "Social Comments" block
    And I configure the "Social Comments" block
    And I click on "Expand all" "link"
    And I set the following fields to these values:
      | Page contexts         | 1        |
      | Display on page types | Any page |
    And I press "Save changes"
    And I am on the "SCCAT2" "category" page
    And I add the "Social Comments" block
    And I configure the "Social Comments" block
    And I click on "Expand all" "link"
    And I set the following fields to these values:
      | Page contexts         | 1        |
      | Display on page types | Any page |
    And I press "Save changes"
    And I turn editing mode off
    And I log out
    When I log in as "student1"
    And I am on "SCC1" course homepage
    And I set the field "Post a comment on this course..." to "Hello from student1"
    And I click on "Post" "button" in the "Social Comments" "block"
    Then I should see "Hello from student1" in the "Social Comments" "block"
    And I am on the "SCCAT1" "category" page
    And I should see "Content only available in course context." in the "Social Comments" "block"
    And I log out
    When I log in as "student2"
    And I am on "SCC2" course homepage
    Then I should not see "Hello from student1" in the "Social Comments" "block"
    When I set the field "Post a comment on this course..." to "Hello from student2"
    And I click on "Post" "button" in the "Social Comments" "block"
    Then I should see "Hello from student2" in the "Social Comments" "block"
    And I log out
    When I log in as "student1"
    And I am on "SCC1" course homepage
    Then I should see "Hello from student1" in the "Social Comments" "block"
    And I should not see "Hello from student2" in the "Social Comments" "block"

  Scenario: Comments stay in their own course within the same category
    Given I log in as "admin"
    And I am on the "SCCAT1" "category" page
    And I turn editing mode on
    And I add the "Social Comments" block
    And I configure the "Social Comments" block
    And I click on "Expand all" "link"
    And I set the following fields to these values:
      | Page contexts         | 1        |
      | Display on page types | Any page |
    And I press "Save changes"
    And I turn editing mode off
    And I log out
    When I log in as "student1"
    And I am on "SCC1" course homepage
    And I set the field "Post a comment on this course..." to "SCC1 comment from student1"
    And I click on "Post" "button" in the "Social Comments" "block"
    Then I should see "SCC1 comment from student1" in the "Social Comments" "block"
    And I log out
    When I log in as "student4"
    And I am on "SCC4" course homepage
    Then "Social Comments" "block" should exist
    And I should not see "SCC1 comment from student1" in the "Social Comments" "block"
    When I set the field "Post a comment on this course..." to "SCC4 comment from student4"
    And I click on "Post" "button" in the "Social Comments" "block"
    Then I should see "SCC4 comment from student4" in the "Social Comments" "block"
    And I log out
    When I log in as "student1"
    And I am on "SCC4" course homepage
    Then I should see "SCC4 comment from student4" in the "Social Comments" "block"
    And I should not see "SCC1 comment from student1" in the "Social Comments" "block"
    When I am on "SCC1" course homepage
    Then I should see "SCC1 comment from student1" in the "Social Comments" "block"
    And I should not see "SCC4 comment from student4" in the "Social Comments" "block"
