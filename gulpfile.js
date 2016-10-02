'use strict';

var gulp = require('gulp');
var gutil = require('gulp-util');
var sass = require('gulp-sass');
var coffee = require('gulp-coffee');
var concat = require('gulp-concat');
var sourcemaps = require('gulp-sourcemaps');

gulp.task('sass', function() {
  return gulp.src('scss/**/*.scss')
    .pipe(sourcemaps.init())
    .pipe(sass.sync().on('error', sass.logError))
    .pipe(sourcemaps.write('.'))
    .pipe(gulp.dest('assets'));
});

gulp.task('coffee', function() {
	return gulp.src(['coffee/*.coffee','coffee/**/*.coffee','crafted/src/coffee/**/*.coffee'])
    .pipe(sourcemaps.init())
		.pipe(coffee().on('error', gutil.log))
    .pipe(concat('events.js'))
		.pipe(gulp.dest('assets'))
    .pipe(sourcemaps.write('.'));
})

gulp.task('watch', function() {
  gulp.watch('scss/**/*.scss', ['sass']);
  gulp.watch('coffee/**/*.coffee', ['coffee'])
});

gulp.task('default', ['watch']);
