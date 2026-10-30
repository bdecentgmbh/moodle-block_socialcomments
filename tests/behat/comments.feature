@block @block_socialcomments @javascript
Feature: Comment, reply, pin and subscribe on a page
  In order to talk about the course with each other
  As a participant
  I need to comment on pages, reply, pin what matters and follow new comments

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Laura     | Bennett  |
      | student1 | Amelia    | Clarke   |
      | student2 | Daniel    | Okafor   |
    And the following "courses" exist:
      | fullname     | shortname |
      | Photo basics | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity | name                  | course | idnumber |
      | page     | Teacher's notes       | C1     | page1    |
    And the following "blocks" exist:
      | blockname      | contextlevel    | reference | pagetypepattern | defaultregion |
      | socialcomments | Activity module | page1     | mod-page-*      | side-pre      |

  Scenario: Comment, reply, edit and delete
    Given I am on the "page1" "Activity" page logged in as "student1"
    When I set the field "Write a comment" to "Why does a small f-number mean a big aperture?"
    And I click on "Post" "button" in the "Social Comments" "block"
    Then I should see "Why does a small f-number mean a big aperture?" in the "Social Comments" "block"
    And I should see "Just now" in the "Social Comments" "block"
    And I should see "1 comment" in the "Social Comments" "block"
    And the field "Subscribe" matches value "1"
    And I log out
    And I am on the "page1" "Activity" page logged in as "student2"
    And I click on "Reply" "button" in the "Social Comments" "block"
    And I set the field "Write a reply" to "It is a fraction of the focal length."
    And I click on "Reply" "button" in the "[data-type='reply']" "css_element"
    And I should see "It is a fraction of the focal length." in the "Social Comments" "block"
    And I open the action menu in ".sc-reply" "css_element"
    And I choose "Edit" in the open action menu
    And I set the field "Write a reply" to "It is a fraction: focal length divided by the f-number."
    And I click on "Save" "button" in the ".sc-reply" "css_element"
    And I should see "It is a fraction: focal length divided by the f-number." in the "Social Comments" "block"
    And I should not see "It is a fraction of the focal length." in the "Social Comments" "block"
    And ".sc-comment > .sc-post .sc-post-menu" "css_element" should not exist
    And I log out
    And I am on the "page1" "Activity" page logged in as "teacher1"
    And I open the action menu in ".sc-comment > .sc-post" "css_element"
    And I choose "Delete" in the open action menu
    And I click on "Delete" "button" in the "Delete" "dialogue"
    Then I should not see "Why does a small f-number mean a big aperture?" in the "Social Comments" "block"
    And I should not see "It is a fraction" in the "Social Comments" "block"
    And I should see "0 comments" in the "Social Comments" "block"

  Scenario: Pin comments and pages and find them on the pinboard
    Given the following config values are set as admin:
      | dateformat | absolute | block_socialcomments |
    And I am on the "page1" "Activity" page logged in as "teacher1"
    And I set the field "Write a comment" to "Remember: the quiz opens on Monday."
    And I click on "Post" "button" in the "Social Comments" "block"
    And I should not see "Just now" in the "Social Comments" "block"
    And I log out
    When I am on the "page1" "Activity" page logged in as "student1"
    And I click on "Pin comment" "button" in the "Social Comments" "block"
    Then "Unpin comment" "button" should exist in the "Social Comments" "block"
    And I click on "Pinboard" "link" in the "Social Comments" "block"
    And I should see "Teacher's notes"
    And I should see "Remember: the quiz opens on Monday."
    And I am on the "page1" "Activity" page
    And I click on "Unpin comment" "button" in the "Social Comments" "block"
    And I click on "Pinboard" "link" in the "Social Comments" "block"
    And I should not see "Remember: the quiz opens on Monday."
    And I am on the "page1" "Activity" page
    And I click on "Pin all comments" "button" in the "Social Comments" "block"
    And "Unpin all comments" "button" should exist in the "Social Comments" "block"
    And I click on "Pinboard" "link" in the "Social Comments" "block"
    And I should see "Remember: the quiz opens on Monday."

  Scenario: The report lists the comments of the course
    Given I am on the "page1" "Activity" page logged in as "student1"
    And I set the field "Write a comment" to "Is the reading for week 1 or 2?"
    And I click on "Post" "button" in the "Social Comments" "block"
    And I log out
    When I am on the "page1" "Activity" page logged in as "teacher1"
    And I click on "Comments report" "link" in the "Social Comments" "block"
    And I should see "Is the reading for week 1 or 2?"
    And I click on "All comments" "link"
    Then I should see "Teacher's notes" in the "report-table" "table"
    And I should see "Is the reading for week 1 or 2?" in the "report-table" "table"
    And I should see "Amelia Clarke" in the "report-table" "table"

  Scenario: In separate groups, each group sees its own comments and those for all groups
    Given the following "groups" exist:
      | name       | course | idnumber |
      | Team North | C1     | G1       |
      | Team South | C1     | G2       |
    And the following "group members" exist:
      | user     | group |
      | student1 | G1    |
      | student2 | G2    |
    And the following "activities" exist:
      | activity | name       | course | idnumber | groupmode |
      | forum    | Photo walk | C1     | forum1   | 1         |
    And the following "blocks" exist:
      | blockname      | contextlevel    | reference | pagetypepattern | defaultregion |
      | socialcomments | Activity module | forum1    | mod-forum-*     | side-pre      |
    And I am on the "forum1" "Activity" page logged in as "teacher1"
    And I set the field "Write a comment" to "Ask before photographing people."
    And I click on "Post" "button" in the "Social Comments" "block"
    And I log out
    And I am on the "forum1" "Activity" page logged in as "student1"
    And I set the field "Write a comment" to "Team North meets on Saturday."
    And I click on "Post" "button" in the "Social Comments" "block"
    And I should see "Team North" in the ".sc-post-group" "css_element"
    And I log out
    When I am on the "forum1" "Activity" page logged in as "student2"
    Then I should see "Ask before photographing people." in the "Social Comments" "block"
    And I should not see "Team North meets on Saturday." in the "Social Comments" "block"
